<?php
/**
 * Main plugin functions for:
 * * checking is a gateway a Bitcoin gateway
 * * generating new wallets
 * * converting fiat<->BTC
 * * generating/getting new addresses for orders
 * * checking addresses for transactions
 * * getting order details for display
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 *
 * - TODO After x unpaid time, mark unpaid orders as failed/cancelled.
 * - TODO: There should be a global cap on how long an address can be assigned without payment. Not something to handle in this class
 * – TODO: hook into post_status changes (+count) to decide to schedule? Or call directly from API class when it assigns an Address to an Order?
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\API_Background_Jobs_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Ensure_Unused_Addresses_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Mark_Address_As_Paid_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\BigDecimal;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\RoundingMode;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\MoneyMismatchException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Trait;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Checkout;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Main API implementation for the Bitcoin Gateway plugin.
 *
 * Handles core functionality including exchange rate conversion between fiat and BTC,
 * wallet and address generation from master public keys (xpub/ypub/zpub), checking
 * Bitcoin addresses for incoming transactions via blockchain APIs, and managing payment
 * confirmation workflows for assigned addresses.
 */
class API implements API_Interface, API_Background_Jobs_Interface, API_WooCommerce_Interface {
	use LoggerAwareTrait;
	use API_WooCommerce_Trait;

	/**
	 * Constructor
	 *
	 * @param Settings_Interface                  $settings The plugin settings.
	 * @param LoggerInterface                     $logger A PSR logger for recording errors, warnings, and debug information.
	 * @param Bitcoin_Wallet_Repository           $bitcoin_wallet_repository Repository for persisting and retrieving wallet master public keys and metadata from WordPress posts.
	 * @param Bitcoin_Address_Repository          $bitcoin_address_repository Repository for saving generated payment addresses and their known status to WordPress posts.
	 * @param Bitcoin_Transaction_Repository      $bitcoin_transaction_repository Repository for storing blockchain transaction data associated with payment addresses in WordPress posts.
	 * @param Blockchain_API_Interface            $blockchain_api Client for querying blockchain APIs (e.g., Blockstream, Blockchain.info) to fetch transactions for payment addresses.
	 * @param Generate_Address_API_Interface      $generate_address_api Service that derives child addresses from a wallet's master public key.
	 * @param Exchange_Rate_API_Interface         $exchange_rate_api Client for fetching current BTC exchange rates from external APIs (e.g., Bitfinex, Bitstamp).
	 * @param Background_Jobs_Scheduler_Interface $background_jobs_scheduler Scheduler for queuing recurring tasks like checking addresses for payments and generating new addresses via Action Scheduler.
	 */
	public function __construct(
		protected Settings_Interface $settings,
		LoggerInterface $logger,
		protected Bitcoin_Wallet_Repository $bitcoin_wallet_repository,
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
		protected Bitcoin_Transaction_Repository $bitcoin_transaction_repository,
		protected Blockchain_API_Interface $blockchain_api,
		protected Generate_Address_API_Interface $generate_address_api,
		protected Exchange_Rate_API_Interface $exchange_rate_api,
		protected Background_Jobs_Scheduler_Interface $background_jobs_scheduler,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Return the cached exchange rate, or fetch it.
	 * Cache for one hour.
	 *
	 * Value of 1 BTC.
	 *
	 * @param Currency $currency The fiat currency to get the BTC exchange rate for (e.g., USD, EUR, GBP).
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the exchange rate API returns invalid data or the currency is not supported.
	 */
	public function get_exchange_rate( Currency $currency ): ?Money {
		$transient_name = 'bh_wp_bitcoin_gateway_exchange_rate_' . $currency->getCurrencyCode();
		/** @var false|array{amount:string,currency:string} $exchange_rate_stored_transient */
		$exchange_rate_stored_transient = get_transient( $transient_name );

		if ( empty( $exchange_rate_stored_transient ) ) {
			try {
				$exchange_rate = $this->exchange_rate_api->get_exchange_rate( $currency );
				// } catch ( Rate_Limit_Exception $e ) {
				//
				// } catch ( UnknownCurrencyException $e ) {
				// return null;
			} catch ( Exception $e ) {
				// TODO: rate limit.
				return null;
			}
			set_transient( $transient_name, $exchange_rate->jsonSerialize(), HOUR_IN_SECONDS );
		} else {
			$exchange_rate = Money::of( $exchange_rate_stored_transient['amount'], $exchange_rate_stored_transient['currency'] );
		}

		return $exchange_rate;
	}

	/**
	 * Get the BTC value of another currency amount.
	 *
	 * Limited currency support: 'USD'|'EUR'|'GBP', maybe others.
	 *
	 * @param Money $fiat_amount The order total amount in fiat currency from the WooCommerce order (stored as a float string in order meta).
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When no exchange rate is available for the given currency.
	 */
	public function convert_fiat_to_btc( Money $fiat_amount ): Money {

		$exchange_rate = $this->get_exchange_rate( $fiat_amount->getCurrency() );

		if ( is_null( $exchange_rate ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'No exchange rate available' );
		}

		// 1 BTC = xx USD.
		$exchange_rate = BigDecimal::of( '1' )->dividedBy( $exchange_rate->getAmount(), 16, RoundingMode::DOWN );

		return $fiat_amount->convertedTo( Currency::of( 'BTC' ), $exchange_rate, null, RoundingMode::DOWN );

		// This is a good number for January 2023, 0.000001 BTC = 0.02 USD.
		// TODO: Calculate the appropriate number of decimals on the fly.
		// $num_decimal_places = 6;
		// $string_result      = (string) wc_round_discount( $float_result, $num_decimal_places + 1 );
		// return $string_result.
	}

	/**
	 * Given an xpub, create the wallet post (if not already existing) and generate addresses until some fresh ones
	 * are generated.
	 *
	 * TODO: refactor this so it can handle 429 rate limiting.
	 *
	 * @param string  $master_public_key The master public key (xpub/ypub/zpub) to derive Bitcoin addresses from for receiving payments.
	 * @param ?string $gateway_id Optional WooCommerce payment gateway ID to associate this wallet with for tracking which gateway created it.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When address generation fails or blockchain API queries encounter errors.
	 */
	public function generate_new_wallet( string $master_public_key, ?string $gateway_id = null ): Wallet_Generation_Result {

		$wallet = $this->bitcoin_wallet_repository->get_by_xpub( $master_public_key )
			?? $this->bitcoin_wallet_repository->save_new( $master_public_key, $gateway_id );

		$existing_fresh_addresses = $this->bitcoin_address_repository->get_addresses(
			wallet: $wallet,
			status: Bitcoin_Address_Status::UNUSED
		);

		$this->ensure_unused_addresses_for_wallet( $wallet );

		$generated_addresses = array();

		$fresh_addresses = $this->bitcoin_address_repository->get_addresses(
			wallet: $wallet,
			status: Bitcoin_Address_Status::UNUSED
		);

		$count = count( $fresh_addresses );
		while ( $count < 20 ) {

			$generate_addresses_result = $this->generate_new_addresses_for_wallet( $wallet );
			$new_generated_addresses   = $generate_addresses_result->new_addresses;

			$generated_addresses = array_merge( $generated_addresses, $new_generated_addresses );

			$check_new_addresses_result = $this->check_addresses_for_transactions( $generated_addresses );

			++$count;
		}

		// TODO: Only return / distinguish which generated addresses are fresh.

		return new Wallet_Generation_Result(
			$wallet,
			$existing_fresh_addresses,
			$generated_addresses
		);
	}

	/**
	 * If a wallet has fewer than 20 fresh addresses available, generate some more.
	 *
	 * @return Addresses_Generation_Result[]
	 * @see API_Interface::generate_new_addresses()
	 * @used-by CLI::generate_new_addresses()
	 * @used-by Background_Jobs_Actions_Handler::generate_new_addresses()
	 */
	public function generate_new_addresses(): array {

		/**
		 * @var array<int, Addresses_Generation_Result> $results
		 */
		$results = array();

		$wallets = $this->bitcoin_wallet_repository->get_all();

		foreach ( $wallets as $wallet ) {
			$results[] = $this->generate_new_addresses_for_wallet( $wallet );
		}

		return $results;
	}

	/**
	 * Check that there are two addresses generated and unused for every wallet (or specific wallet/number).
	 *
	 * TODO: If a store has 100 orders/minute, this should still only check each address once every ten minutes, since
	 * until a new block is mined, the result won't change. TODO: mempool?
	 *
	 * Payment addresses may be used outside WordPress and if we were to reuse those addresses, confirming the payment
	 * can't be done confidently. (TODO: still only consider transactions made after the address is assigned to an order).
	 *
	 * @used-by Background_Jobs_Actions_Interface::ensure_unused_addresses()
	 *
	 * @param int              $required_count The number of addresses to be sure have not been used yet. There is no real point checking more than 2, especially if using free APIs with low rate limits.
	 * @param Bitcoin_Wallet[] $wallets The Bitcoin Wallets to check. When on a recurring schedule, check all; when at checkout using a specific wallet, only check that one.
	 *
	 * @return array<string, Ensure_Unused_Addresses_Result> array<wallet_xpub: Ensure_Unused_Addresses_Result>
	 *
	 * @throws Rate_Limit_Exception TODO: I think this should be caught before here, the job rescheduled and that fact recorded in function response (Ensure_Unused_Addresses_Result probably isn't enough, so).
	 */
	public function ensure_unused_addresses( int $required_count = 2, array $wallets = array() ): array {
		// TODO: write a test to see is wallet status being used correctly, then add filter: `Bitcoin_Wallet_Status::ACTIVE`.
		$wallets = ! empty( $wallets ) ? $wallets : $this->bitcoin_wallet_repository->get_all();

		/** @var array<int, array<Bitcoin_Address>> $assumed_existing_unused_addresses Wallet post id:Bitcoin_Address[] */
		$assumed_existing_unused_addresses = array();
		/** @var array<int, array<Bitcoin_Address>> $actual_unused_addresses_by_wallet Wallet post id:Bitcoin_Address[] */
		$actual_unused_addresses_by_wallet = array();
		/** @var array<int, array<Bitcoin_Address>> $unexpectedly_used_addresses_by_wallet Wallet post id:Bitcoin_Address[] */
		$unexpectedly_used_addresses_by_wallet = array();
		/** @var array<int, array<Bitcoin_Address>> $new_addresses_by_wallet Wallet post id:Bitcoin_Address[] */
		$new_addresses_by_wallet = array();
		foreach ( $wallets as $wallet ) {
			$assumed_existing_unused_addresses[ $wallet->get_post_id() ]     = array();
			$actual_unused_addresses_by_wallet[ $wallet->get_post_id() ]     = array();
			$unexpectedly_used_addresses_by_wallet[ $wallet->get_post_id() ] = array();
			$new_addresses_by_wallet[ $wallet->get_post_id() ]               = array();
		}

		// Sort by last updated (checked) and get two per wallet.
		// TODO: check the modified time and assume any that were checked in the past ten minutes are still valid (since no new block has been completed since).
		$unused_addresses = $this->bitcoin_address_repository->get_unused_bitcoin_addresses();

		foreach ( $unused_addresses as $address ) {
			$address_wallet_id = $address->get_wallet_parent_post_id();
			if ( count( $actual_unused_addresses_by_wallet[ $address_wallet_id ] ) >= $required_count ) {
				continue;
			}

			// TODO: Should we index by anything?
			$assumed_existing_unused_addresses[ $address_wallet_id ][] = $address;

			// TODO: handle rate limits.
			$address_transactions_result = $this->update_address_transactions( $address );
			if ( empty( $address_transactions_result->address->get_tx_ids() ) ) {
				$actual_unused_addresses_by_wallet[ $address_wallet_id ][] = $address;
			} else {
				$unexpectedly_used_addresses_by_wallet[ $address_wallet_id ][] = $address;
				// TODO: log more.
			}
		}

		/**
		 * @param array<int, array<Bitcoin_Address>> $unused_addresses_by_wallet
		 * @param int $required_count
		 */
		$all_wallets_have_enough_addresses_fn = function ( array $unused_addresses_by_wallet, int $required_count ): bool {
			return array_reduce(
				$unused_addresses_by_wallet,
				function ( bool $carry, array $addresses ) use ( $required_count ): bool {
					return $carry && count( $addresses ) >= $required_count;
				},
				true
			);
		};

		// This could loop hundreds of time, e.g. you add a wallet that has been in use elsewhere and it has
		// to check each used address until it finds an unused one.
		while ( ! $all_wallets_have_enough_addresses_fn( $actual_unused_addresses_by_wallet, $required_count ) ) {
			foreach ( $wallets as $wallet ) {
				if ( count( $actual_unused_addresses_by_wallet[ $wallet->get_post_id() ] ) < $required_count ) {
					$address_generation_result   = $this->generate_new_addresses_for_wallet( $wallet, 1 );
					$new_address                 = array_first( $address_generation_result->new_addresses );
					$address_transactions_result = $this->update_address_transactions( $new_address );
					if ( empty( $address_transactions_result->address->get_tx_ids() ) ) {
						$actual_unused_addresses_by_wallet[ $wallet->get_post_id() ][] = $new_address;
						$new_addresses_by_wallet[ $wallet->get_post_id() ][]           = $new_address;
					}
				}
			}
		}

		/** @var array<string, Ensure_Unused_Addresses_Result> $result_by_wallet */
		$result_by_wallet = array();

		foreach ( $wallets as $wallet ) {
			$result_by_wallet[ $wallet->get_xpub() ] = new Ensure_Unused_Addresses_Result(
				wallet: $wallet,
				assumed_existing_unused_addresses: $assumed_existing_unused_addresses[ $wallet->get_post_id() ],
				actual_existing_unused_addresses: $actual_unused_addresses_by_wallet[ $wallet->get_post_id() ],
				unexpectedly_used_addresses_by_wallet: $unexpectedly_used_addresses_by_wallet[ $wallet->get_post_id() ],
				new_unused_addresses: $new_addresses_by_wallet[ $wallet->get_post_id() ],
			);
		}

		return $result_by_wallet;
	}

	/**
	 * Ensure a specific wallet has the required number of verified unused addresses available.
	 *
	 * @used-by Checkout::ensure_one_address_for_payment()
	 * @see Bitcoin_Gateway::process_payment()
	 *
	 * @param Bitcoin_Wallet $wallet The wallet to generate unused addresses for by querying the blockchain to verify generated addresses have no transaction history.
	 * @param int            $required_count The minimum number of unused addresses that must be available for this wallet before returning.
	 */
	public function ensure_unused_addresses_for_wallet( Bitcoin_Wallet $wallet, int $required_count = 2 ): Ensure_Unused_Addresses_Result {
		return $this->ensure_unused_addresses( $required_count, array( $wallet ) )[ $wallet->get_xpub() ];
	}

	/**
	 * Derive new Bitcoin addresses for a saved wallet.
	 *
	 * @param Bitcoin_Wallet $wallet The wallet to generate child addresses from using its master public key and current address index.
	 * @param int            $generate_count The number of sequential addresses to derive from the wallet's next available derivation path index. 20 is the standard lookahead for wallets.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When address derivation fails or addresses cannot be saved to the database.
	 */
	public function generate_new_addresses_for_wallet( Bitcoin_Wallet $wallet, int $generate_count = 2 ): Addresses_Generation_Result {

		$address_index = $wallet->get_address_index();

		/** @var non-empty-array<Bitcoin_Address> $generated_addresses */
		$generated_addresses       = array();
		$generated_addresses_count = 0;

		do {
			// TODO: Post increment or we will never generate address 0 like this.
			++$address_index;

			$new_address_string = $this->generate_address_api->generate_address( $wallet->get_xpub(), $address_index );

			if ( ! is_null( $this->bitcoin_address_repository->get_post_id_for_address( $new_address_string ) ) ) {
				// Although inefficient to run this inside the loop, overall, searching past the known index could cause a PHP timeout.
				// (emphasizing that this should be run as a scheduled task).
				$this->bitcoin_wallet_repository->set_highest_address_index( $wallet, $address_index );
				continue;
			}

			$bitcoin_address = $this->bitcoin_address_repository->save_new(
				wallet: $wallet,
				derivation_path_sequence_index: $address_index,
				address: $new_address_string,
			);

			$generated_addresses[] = $bitcoin_address;

			++$generated_addresses_count;

		} while ( $generated_addresses_count < $generate_count );

		$this->bitcoin_wallet_repository->set_highest_address_index( $wallet, $address_index );

		/**
		 * @see self::check_addresses_for_transactions()
		 */
		$this->background_jobs_scheduler->schedule_single_ensure_unused_addresses( $wallet );

		// TODO: Should probably refresh wallet here... TODO: make sure to record any changes (previous address index etc) in the result object.

		return new Addresses_Generation_Result(
			wallet: $wallet,
			new_addresses: $generated_addresses,
			address_index: $address_index,
		);
	}

	/**
	 * Check newly generated addresses with "unknown" status for incoming transactions.
	 *
	 * @used-by Background_Jobs_Actions_Handler::check_new_addresses_for_transactions()
	 *
	 * @return Check_Assigned_Addresses_For_Transactions_Result Result containing the count of addresses checked before rate limiting or completion.
	 * @throws Rate_Limit_Exception When the blockchain API's rate limit is exceeded, containing the reset time for rescheduling the job.
	 */
	public function check_new_addresses_for_transactions(): Check_Assigned_Addresses_For_Transactions_Result {

		$addresses = $this->bitcoin_address_repository->get_unknown_bitcoin_addresses();

		if ( empty( $addresses ) ) {
			$this->logger->debug( 'No addresses with "unknown" status to check' );

			return new Check_Assigned_Addresses_For_Transactions_Result(
				count: 0
			); // TODO: return something meaningful.
		}

		return $this->check_addresses_for_transactions( $addresses );
	}

	/**
	 * Query the blockchain for transactions on multiple addresses and update their status.
	 *
	 * @used-by Background_Jobs_Actions_Handler::check_new_addresses_for_transactions()
	 *
	 * @param Bitcoin_Address[] $addresses Array of address objects to query for transactions and save results to the database.
	 *
	 * @return Check_Assigned_Addresses_For_Transactions_Result Result containing the count of addresses successfully checked before encountering rate limits or errors.
	 *
	 * @throws Rate_Limit_Exception When the blockchain API rate limit (HTTP 429) is hit, so the job can be rescheduled using the exception's reset time.
	 */
	protected function check_addresses_for_transactions( array $addresses ): Check_Assigned_Addresses_For_Transactions_Result {

		$result = array();

		try {
			foreach ( $addresses as $bitcoin_address ) {
				$result[ $bitcoin_address->get_raw_address() ] = $this->update_address_transactions( $bitcoin_address );
			}
		} catch ( Rate_Limit_Exception $exception ) {
			// Reschedule if we hit 429 (there will always be at least one address to check if it 429s.).

			$this->background_jobs_scheduler->schedule_check_newly_generated_bitcoin_addresses_for_transactions(
				datetime: $exception->get_reset_time()
			);

			return new Check_Assigned_Addresses_For_Transactions_Result(
				count: count( $result )
			);
		} catch ( Exception $exception ) {
			$this->logger->error( $exception->getMessage() );

			$this->background_jobs_scheduler->schedule_check_newly_generated_bitcoin_addresses_for_transactions(
				( new DateTimeImmutable() )->add( new DateInterval( 'PT15M' ) ),
			);
		}

		// TODO: After this is complete, there could be 0 fresh addresses (e.g. if we start at index 0 but 200 addresses
		// are already used). => We really need to generate new addresses until we have some.

		// TODO: Return something useful.
		return new Check_Assigned_Addresses_For_Transactions_Result(
			count: count( $result )
		);
	}

	/**
	 * Remotely check/fetch the latest data for an address.
	 *
	 * TODO: return an object show the initial and final state in a way the changes made can be logged. E.g. was it ever checked before?
	 *
	 * @param Bitcoin_Address $address The Bitcoin address to query the blockchain API for, retrieving all transactions where this address received funds.
	 *
	 * @throws Rate_Limit_Exception When the blockchain API returns HTTP 429, indicating too many requests and the service should back off until the reset time.
	 */
	public function update_address_transactions( Bitcoin_Address $address ): Update_Address_Transactions_Result {
		// TODO: sort by last updated.
		// TODO: retry on rate limit.

		/** @var array<int, Bitcoin_Transaction> $transactions_by_post_ids */
		$transactions_by_post_ids = array();

		/** @var array<int, string> $tx_ids_by_post_ids */
		$tx_ids_by_post_ids = array();

		$updated_transactions = $this->blockchain_api->get_transactions_received( btc_address: $address->get_raw_address() );

		foreach ( $updated_transactions as $transaction ) {
			$saved_transaction                                       = $this->bitcoin_transaction_repository->save_new(
				$transaction,
				$address
			);
			$tx_ids_by_post_ids[ $saved_transaction->get_post_id() ] = $saved_transaction->get_txid();
			$transactions_by_post_ids[ $saved_transaction->get_post_id() ] = $saved_transaction;
		}

		/**
		 * Save an array of post_id:tx_id to the address object for quick reference, e.g. before/after checks.
		 */
		$this->bitcoin_transaction_repository->associate_transactions_post_ids_to_address( $tx_ids_by_post_ids, $address );
		// TODO: refresh. make sure to record changes for the result object.
		// $address = $this->bitcoin_address_repository->refresh($address).

		if ( $address->get_status() === Bitcoin_Address_Status::UNKNOWN ) {
			$this->bitcoin_address_repository->set_status(
				$address,
				status: ( 0 === count( $updated_transactions ) ) ? Bitcoin_Address_Status::UNUSED : Bitcoin_Address_Status::USED
			);
		}

		// TODO: run a check on the address to see has the amount been paid, then  update the address status/state.

		// TODO: do_action on changes for logging.

		// TODO: Check are any previous transactions no longer present!!! (unlikely?).

		return new Update_Address_Transactions_Result(
			address: $this->bitcoin_address_repository->refresh( $address ),
			known_tx_ids_before: $address->get_tx_ids(),
			all_transactions: $transactions_by_post_ids,
		);

		// Throws when e.g. API is offline.
		// TODO: log, rate limit, notify.
	}

	/**
	 * @see Background_Jobs_Actions_Interface::check_assigned_addresses_for_transactions()
	 * @used-by Background_Jobs_Actions_Handler::check_assigned_addresses_for_transactions()
	 */
	public function check_assigned_addresses_for_payment(): Check_Assigned_Addresses_For_Transactions_Result {

		foreach ( $this->bitcoin_address_repository->get_assigned_bitcoin_addresses() as $bitcoin_address ) {
			$this->check_address_for_payment( $bitcoin_address );
		}
		// TODO: Return actual result with count of addresses checked.
		return new Check_Assigned_Addresses_For_Transactions_Result( count:0 );
	}

	/**
	 * Check a Bitcoin address for payment and mark as paid if sufficient funds received.
	 *
	 * @param Bitcoin_Address $bitcoin_address The Bitcoin address to check.
	 */
	protected function check_address_for_payment( Bitcoin_Address $bitcoin_address ): void {

		$updated_transactions = $this->update_address_transactions( $bitcoin_address );

		$total_received = $this->get_address_confirmed_balance(
			raw_address: $bitcoin_address->get_raw_address(),
			blockchain_height: 123,
			required_confirmations: 3,
			transactions: $updated_transactions->all_transactions
		);

		$target_amount = $bitcoin_address->get_target_amount();

		if ( ! $target_amount ) {
			return;
		}

		$is_paid = $total_received->isGreaterThanOrEqualTo( $target_amount );

		if ( $is_paid ) {
			$this->mark_address_as_paid( $bitcoin_address );
		}

		// TODO: Return result object with payment status and transaction details.
	}

	/**
	 * Mark a Bitcoin address as paid and notify integrations.
	 *
	 * TODO: maybe split this into ~"set address status to used" and ~"fire action to alert integrations".
	 *
	 * @used-by self::check_address_for_payment()
	 *
	 * @param Bitcoin_Address $bitcoin_address The Bitcoin address to mark as paid.
	 */
	protected function mark_address_as_paid( Bitcoin_Address $bitcoin_address ): Mark_Address_As_Paid_Result {

		$status_before = $bitcoin_address->get_status();

		$this->bitcoin_address_repository->set_status(
			address: $bitcoin_address,
			status: Bitcoin_Address_Status::USED
		);

		$order_post_id = $bitcoin_address->get_order_id();

		if ( ! $order_post_id ) {
			return new Mark_Address_As_Paid_Result(
				$bitcoin_address,
				$status_before,
			);
		}

		/** @var class-string $order_post_type */
		$order_post_type = get_post_type( $order_post_id );

		// TODO: Add `phpstan-type` on the Bitcoin_Address class importable by consumers.
		$address_array = (array) $bitcoin_address;

		/**
		 * TODO: Maybe this should be a filter to learn who used the action(filter).
		 *
		 * @phpstan-type array{} Bitcoin_Address_Array
		 *
		 * @param class-string $order_post_type
		 * @param int $order_post_id
		 * @param array{} $address_array
		 */
		do_action( 'bh_wp_bitcoin_gateway_payment_received', $order_post_type, $order_post_id, $address_array );

		return new Mark_Address_As_Paid_Result(
			$bitcoin_address,
			$status_before,
		);
	}

	/**
	 * Get saved transactions for a Bitcoin address (`null` if never checked).
	 *
	 * @param Bitcoin_Address $bitcoin_address The Bitcoin address to get transactions for.
	 * @return ?array<Bitcoin_Transaction|Transaction_Interface>
	 * @throws BH_WP_Bitcoin_Gateway_Exception If one of the post IDs does not match the transaction post type.
	 */
	public function get_saved_transactions( Bitcoin_Address $bitcoin_address ): ?array {
		return $this->bitcoin_transaction_repository->get_transactions_for_address( $bitcoin_address );
	}

	/**
	 * From the received transactions, sum those who have enough confirmations.
	 *
	 * @param string                  $raw_address The raw Bitcoin address to calculate balance for.
	 * @param int                     $blockchain_height The current blockchain height. (TODO: explain why).
	 * @param int                     $required_confirmations A confirmation is a subsequent block mined after the transaction.
	 * @param Transaction_Interface[] $transactions Array of transactions to calculate balance from.
	 *
	 * @throws MoneyMismatchException If the calculations were somehow using two different currencies.
	 * @throws UnknownCurrencyException If `BTC` has not correctly been added to Money's currency list.
	 */
	public function get_address_confirmed_balance( string $raw_address, int $blockchain_height, int $required_confirmations, array $transactions ): Money {
		return array_reduce(
			$transactions,
			function ( Money $carry, Transaction_Interface $transaction ) use ( $raw_address, $blockchain_height, $required_confirmations ) {
				if ( ( $blockchain_height - $transaction->get_block_height() ) > $required_confirmations ) {
					return $carry->plus( $this->get_value_for_transaction( $raw_address, $transaction ) );
				}
				return $carry;
			},
			Money::of( 0, 'BTC' )
		);
	}

	/**
	 * Sum the transaction's vector-outputs sent to a specific address.
	 *
	 * @param string                $to_address The Bitcoin address to calculate value for.
	 * @param Transaction_Interface $transaction The transaction to calculate value from.
	 */
	private function get_value_for_transaction( string $to_address, Transaction_Interface $transaction ): Money {

		$value_including_fee = array_reduce(
			$transaction->get_v_out(),
			function ( Money $carry, Transaction_VOut $out ) use ( $to_address ) {
				if ( $to_address === $out->scriptpubkey_address ) {
					return $carry->plus( $out->value );
				}
				return $carry;
			},
			Money::of( 0, 'BTC' )
		);

		return $value_including_fee->dividedBy( 100_000_000 );
	}
}
