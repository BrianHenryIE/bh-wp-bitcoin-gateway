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
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Ensure_Unused_Addresses_Result;
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
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use DateInterval;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class API implements API_Interface, API_Background_Jobs_Interface, API_WooCommerce_Interface {
	use LoggerAwareTrait;
	use API_WooCommerce_Trait;

	/**
	 * Constructor
	 *
	 * @param Settings_Interface                  $settings The plugin settings.
	 * @param LoggerInterface                     $logger A PSR logger.
	 * @param Bitcoin_Wallet_Repository           $bitcoin_wallet_repository Wallet repository.
	 * @param Bitcoin_Address_Repository          $bitcoin_address_repository Repository to save and fetch addresses from wp_posts.
	 * @param Bitcoin_Transaction_Repository      $bitcoin_transaction_repository
	 * @param Blockchain_API_Interface            $blockchain_api The object/client to query the blockchain for transactions.
	 * @param Generate_Address_API_Interface      $generate_address_api Object that does the maths to generate new addresses for a wallet.
	 * @param Exchange_Rate_API_Interface         $exchange_rate_api Object/client to fetch the exchange rate.
	 * @param Background_Jobs_Scheduler_Interface $background_jobs_scheduler
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
	 * @param Currency $currency
	 *
	 * @throws Exception
	 */
	public function get_exchange_rate( Currency $currency ): ?Money {
		$transient_name = 'bh_wp_bitcoin_gateway_exchange_rate_' . $currency->getCurrencyCode();
		/** @var false|array{amount:string,currency:string} $exchange_rate_stored_transient */
		$exchange_rate_stored_transient = get_transient( $transient_name );

		if ( empty( $exchange_rate_stored_transient ) ) {
			try {
				$exchange_rate = $this->exchange_rate_api->get_exchange_rate( $currency );
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
	 * @param Money $fiat_amount This is stored in the WC_Order object as a float (as a string in meta).
	 */
	public function convert_fiat_to_btc( Money $fiat_amount ): Money {

		$exchange_rate = $this->get_exchange_rate( $fiat_amount->getCurrency() );

		if ( is_null( $exchange_rate ) ) {
			throw new Exception( 'No exchange rate available' );
		}

		// 1 BTC = xx USD.
		$exchange_rate = BigDecimal::of( '1' )->dividedBy( $exchange_rate->getAmount(), 16, RoundingMode::DOWN );

		return $fiat_amount->convertedTo( Currency::of( 'BTC' ), $exchange_rate, null, RoundingMode::DOWN );

		// This is a good number for January 2023, 0.000001 BTC = 0.02 USD.
		// TODO: Calculate the appropriate number of decimals on the fly.
		// $num_decimal_places = 6;
		// $string_result      = (string) wc_round_discount( $float_result, $num_decimal_places + 1 );
		// return $string_result;
	}

	/**
	 * Given an xpub, create the wallet post (if not already existing) and generate addresses until some fresh ones
	 * are generated.
	 *
	 * TODO: refactor this so it can handle 429 rate limiting.
	 *
	 * @param string  $master_public_key Xpub/ypub/zpub string.
	 * @param ?string $gateway_id
	 *
	 * @throws Exception
	 */
	public function generate_new_wallet( string $master_public_key, ?string $gateway_id = null ): Wallet_Generation_Result {

		$wallet = $this->bitcoin_wallet_repository->get_by_xpub( $master_public_key )
			?? $this->bitcoin_wallet_repository->save_new( $master_public_key, $gateway_id );

		$existing_fresh_addresses = $this->bitcoin_address_repository->get_addresses(
			wallet: $wallet,
			status: Bitcoin_Address_Status::UNUSED
		);

		$this->ensure_unused_addresses_for_wallet( $wallet );

		// get_fresh_addresses()

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
	 * @used-by Background_Jobs_Actions_Interface::ensure_unused_addresses()
	 *
	 * @return array<string, Ensure_Unused_Addresses_Result> array<wallet_xpub: Ensure_Unused_Addresses_Result>
	 */
	public function ensure_unused_addresses( int $required_count = 2, array $wallets = array() ): array {
		// $this->bitcoin_wallet_repository->get_all(Bitcoin_Wallet_Status::ACTIVE);
		$wallets = ! empty( $wallets ) ? $wallets : $this->bitcoin_wallet_repository->get_all();

		/** @var array<string, Ensure_Unused_Addresses_Result> $result_by_wallet */
		$result_by_wallet = array();

		$unused_addresses_by_wallet = array();
		foreach ( $wallets as $wallet ) {
			$unused_addresses_by_wallet[ $wallet->get_post_id() ] = array();
			$result_by_wallet[ $wallet->get_xpub() ]              = array();
		}

		// Sort by last updated (checked) and get two per wallet.
		// TODO: use modified time and assume any that were checked in the past ten minutes are still valid (since no new block has been completed)
		$unused_addresses = $this->bitcoin_address_repository->get_unused_bitcoin_addresses();
		foreach ( $unused_addresses as $address ) {
			$address_wallet_id = $address->get_wallet_parent_post_id();
			if ( count( $unused_addresses_by_wallet[ $address_wallet_id ] ) >= $required_count ) {
				continue;
			}

			// TODO: handle rate limits.
			$address_transactions_result = $this->update_address_transactions( $address );
			if ( empty( $address_transactions_result->address->get_tx_ids() ) ) {
				$unused_addresses_by_wallet[ $address_wallet_id ] = $address;
			}
		}

		$all_wallets_have_enough_addresses = function ( array $unused_addresses_by_wallet, int $required_count ): bool {
			return array_reduce(
				$unused_addresses_by_wallet,
				function ( bool $carry, array $addresses ) use ( $required_count ) {
					return $carry && count( $addresses ) >= $required_count;
				},
				true
			);
		};

		// This could loop hundreds of time, e.g. you add a wallet that has been in use elsewhere and it has
		// to check each used address until it finds an unused one.
		while ( ! $all_wallets_have_enough_addresses( $unused_addresses_by_wallet, $required_count ) ) {
			foreach ( $wallets as $wallet ) {
				if ( count( $unused_addresses_by_wallet[ $wallet->get_post_id() ] ) < $required_count ) {
					$address_generation_result   = $this->generate_new_addresses_for_wallet( $wallet, 1 );
					$new_address                 = array_first( $address_generation_result->new_addresses );
					$address_transactions_result = $this->update_address_transactions( $new_address );
					if ( empty( $address_transactions_result->address->get_tx_ids() ) ) {
						$unused_addresses_by_wallet[ $wallet->get_post_id() ][] = $new_address;
					}
				}
			}
		}

		return $result_by_wallet;
	}

	public function ensure_unused_addresses_for_wallet( Bitcoin_Wallet $wallet, int $required_count = 2 ): Ensure_Unused_Addresses_Result {
		return $this->ensure_unused_addresses( $required_count, array( $wallet ) )[ $wallet->get_xpub() ];
	}

	/**
	 * @param Bitcoin_Wallet $wallet
	 * @param int            $generate_count // TODO:  20 is the standard lookahead for wallets. cite.
	 *
	 * @throws Exception When no wallet object is found for the master public key (xpub) string.
	 */
	public function generate_new_addresses_for_wallet( Bitcoin_Wallet $wallet, int $generate_count = 2 ): Addresses_Generation_Result {

		$address_index = $wallet->get_address_index();

		$generated_addresses       = array();
		$generated_addresses_count = 0;

		do {
			// TODO: Post increment or we will never generate address 0 like this.
			++$address_index;

			$new_address_string = $this->generate_address_api->generate_address( $wallet->get_xpub(), $address_index );

			if ( ! is_null( $this->bitcoin_address_repository->get_post_id_for_address( $new_address_string ) ) ) {
				continue;
			}

			$bitcoin_address = $this->bitcoin_address_repository->save_new(
				wallet: $wallet,
				derivation_path_sequence_index: $address_index,
				xpub: $new_address_string,
			);

			$generated_addresses[] = $bitcoin_address;

			++$generated_addresses_count;

		} while ( $generated_addresses_count < $generate_count );

		$this->bitcoin_wallet_repository->set_highest_address_index( $wallet, $address_index );

		/**
		 * @see self::check_addresses_for_transactions()
		 */
		$this->background_jobs_scheduler->schedule_single_ensure_unused_addresses();

		return new Addresses_Generation_Result(
			wallet: $wallet,
			new_addresses: $generated_addresses,
			address_index: $address_index,
		);
	}

	/**
	 * @used-by Background_Jobs_Actions_Handler::check_new_addresses_for_transactions()
	 *
	 * @return Check_Assigned_Addresses_For_Transactions_Result (was: array<string, array<string, Transaction_Interface>>)
	 * @throws Rate_Limit_Exception
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
	 * @used-by Background_Jobs_Actions_Handler::check_new_addresses_for_transactions()
	 *
	 * @param Bitcoin_Address[] $addresses Array of address objects to query and update.
	 *
	 * @return Check_Assigned_Addresses_For_Transactions_Result (was array<string, array<string, Transaction_Interface>>))
	 *
	 * @throws Rate_Limit_Exception
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
	 * @param Bitcoin_Address $address The address object to query.
	 *
	 * @return array<string, Transaction_Interface|Bitcoin_Transaction>
	 *
	 * @throws Rate_Limit_Exception
	 */
	public function update_address_transactions( Bitcoin_Address $address ): Update_Address_Transactions_Result {

		// TODO: sort by last updated

		// TODO: retry on rate limit.
		/** @var Bitcoin_Transaction[] $transactions */
		$transactions = array();
		try {
			$transactions_by_post_ids = array();
			$updated_transactions     = $this->blockchain_api->get_transactions_received( btc_address: $address->get_raw_address() );
			foreach ( $updated_transactions as $transaction ) {
				$saved_transaction = $this->bitcoin_transaction_repository->save_new(
					$transaction,
					$address
				);
				$transactions[]    = $saved_transaction;
				$transactions_by_post_ids[ $saved_transaction->get_post_id() ] = $saved_transaction->get_txid();
			}
			/**
			 * Save an array of post_id:tx_id to the address object for quick reference, e.g. before/after checks.
			 */
			$this->bitcoin_transaction_repository->associate_transactions_post_ids_to_address( $transactions_by_post_ids, $address );

			if ( $address->get_status() === Bitcoin_Address_Status::UNKNOWN ) {
				$this->bitcoin_address_repository->set_status(
					$address,
					status: 0 === count( $updated_transactions ) ? Bitcoin_Address_Status::UNUSED : Bitcoin_Address_Status::USED
				);
			}

			// TODO: run a check on the address to see has the amount been paid, then  update the address status/state.

			// TODO: do_action on changes for logging.

			// TODO: Check are any previous transactions no longer present!!! (unlikely?)

			return new Update_Address_Transactions_Result(
				address: $this->bitcoin_address_repository->get_by_post_id( $address->get_post_id() ),
				// known_tx_ids_before: $address->get_tx_ids(),
				new_transactions: $transactions,
			);
		} catch ( Rate_Limit_Exception $_exception ) {
			return $this->bitcoin_transaction_repository->get_transactions_for_address( $address ) ?? array();
		} catch ( Exception $_exception ) {
			// E.g. API is offline.
			// TODO: log, rate limit, notify.
			// TODO: is empty array ok here?
			return $this->bitcoin_transaction_repository->get_transactions_for_address( $address ) ?? array();
		}
	}

	/**
	 * TODO: The return value should be a structured summary that can be used in a log message.
	 *
	 * @see Background_Jobs_Actions_Interface::check_assigned_addresses_for_transactions()
	 * @used-by Background_Jobs_Actions_Handler::check_assigned_addresses_for_transactions()
	 */
	public function check_assigned_addresses_for_payment(): Check_Assigned_Addresses_For_Transactions_Result {

		foreach ( $this->bitcoin_address_repository->get_assigned_bitcoin_addresses() as $bitcoin_address ) {
			$this->check_address_for_payment( $bitcoin_address );
		}
		// TODO:
		return new Check_Assigned_Addresses_For_Transactions_Result( count:0 );
	}

	protected function check_address_for_payment( Bitcoin_Address $bitcoin_address ): void {

		$updated_transactions = $this->update_address_transactions( $bitcoin_address );

		$total_received = $this->get_address_confirmed_balance(
			raw_address: $bitcoin_address->get_raw_address(),
			blockchain_height: 123,
			required_confirmations: 3,
			transactions: $updated_transactions
		);

		$target_amount = $bitcoin_address->get_target_amount();

		if ( ! $target_amount ) {
			return;
		}

		$is_paid = $total_received->isGreaterThanOrEqualTo( $target_amount );

		if ( $is_paid ) {
			$this->mark_address_as_paid( $bitcoin_address );
		}
	}

	protected function mark_address_as_paid( Bitcoin_Address $bitcoin_address ) {

		// TODO: Change the post status.
		// $bitcoin_address->get_post_id()

		$order_post_id = $bitcoin_address->get_order_id();

		if ( ! $order_post_id ) {
			return;
		}

		/** @var class-string $order_post_type */
		$order_post_type = get_post_type( $order_post_id );

		// TODO: Add `phpstan-type` on the Bitcoin_Address class importable by consumers.
		$address_array = (array) $bitcoin_address;

		/**
		 * @phpstan-type array{} Bitcoin_Address_Array
		 *
		 * @param class-string $order_post_type
		 * @param int $order_post_id
		 * @param array{} $address_array
		 */
		do_action( 'bh_wp_bitcoin_gateway_payment_received', $order_post_type, $order_post_id, $address_array );
	}

	/**
	 * @param Bitcoin_Address $bitcoin_address
	 *
	 * @return Bitcoin_Transaction[]
	 */
	public function get_saved_transactions( Bitcoin_Address $bitcoin_address ): ?array {
		return $this->bitcoin_transaction_repository->get_transactions_for_address( $bitcoin_address );
	}

	/**
	 * From the received transactions, sum those who have enough confirmations.
	 *
	 * @param string                  $raw_address
	 * @param int                     $blockchain_height The current blockchain height. (TODO: explain why).
	 * @param int                     $required_confirmations A confirmation is a subsequent block mined after the transaction.
	 * @param Transaction_Interface[] $transactions
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
	 * Either the inputs or outputs of related transactions ~sum to the value of the address
	 */
	private function get_value_for_transaction( string $to_address, Transaction_Interface $transaction ): Money {

		$value_including_fee = array_reduce(
			$transaction->get_v_out(),
			function ( Money $carry, Transaction_VOut $out ) use ( $to_address ) {
				if ( in_array( $to_address, $out->scriptPubKey->addresses, true ) ) {
					return $carry->plus( $out->value );
				}
				return $carry;
			},
			Money::of( 0, 'BTC' )
		);

		return $value_including_fee->dividedBy( 100_000_000 );
	}
}
