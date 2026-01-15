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
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Address_For_Payment_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Ensure_Unused_Addresses_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Mark_Address_As_Paid_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\MoneyMismatchException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Trait;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
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
	 * @param Exchange_Rate_Service               $exchange_rate_service Client for fetching current BTC exchange rates from external APIs (e.g., Bitfinex, Bitstamp).
	 * @param Bitcoin_Wallet_Service              $wallet_service Generating wallets and payment addresses.
	 * @param Payment_Service                     $payment_service Service for confirming payments.
	 * @param Background_Jobs_Scheduler_Interface $background_jobs_scheduler Scheduler for queuing recurring tasks like checking addresses for payments and generating new addresses via Action Scheduler.
	 * @param LoggerInterface                     $logger A PSR logger for recording errors, warnings, and debug information.
	 */
	public function __construct(
		protected Settings_Interface $settings,
		protected Exchange_Rate_Service $exchange_rate_service,
		protected Bitcoin_Wallet_Service $wallet_service,
		protected Payment_Service $payment_service,
		protected Background_Jobs_Scheduler_Interface $background_jobs_scheduler,
		LoggerInterface $logger,
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
		return $this->exchange_rate_service->get_exchange_rate( $currency );
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
		return $this->exchange_rate_service->convert_fiat_to_btc( $fiat_amount );
	}

	/**
	 *
	 *
	 * @param string      $xpub
	 * @param string|null $gateway_id
	 * @throws BH_WP_Bitcoin_Gateway_Exception
	 */
	public function get_wallet_for_master_public_key( string $xpub, ?string $gateway_id = null ): Wallet_Generation_Result {
		$result = $this->wallet_service->get_wallet_for_xpub( $xpub, $gateway_id );

		if ( $result->is_new ) {
			$this->background_jobs_scheduler->schedule_single_ensure_unused_addresses( $result->wallet );
		}

		return new Wallet_Generation_Result(
			get_wallet_for_xpub_service_result: $result,
			did_schedule_ensure_addresses: $result->is_new,
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
		return $this->wallet_service->generate_new_addresses();
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
		$wallets = ! empty( $wallets ) ? $wallets : $this->wallet_service->get_all_wallets();

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
		$unused_addresses = $this->wallet_service->get_unused_bitcoin_addresses();

		foreach ( $unused_addresses as $address ) {
			$address_wallet_id = $address->get_wallet_parent_post_id();
			if ( count( $actual_unused_addresses_by_wallet[ $address_wallet_id ] ) >= $required_count ) {
				continue;
			}

			// TODO: Should we index by anything?
			$assumed_existing_unused_addresses[ $address_wallet_id ][] = $address;

			// TODO: handle rate limits.
			$address_transactions_result = $this->payment_service->update_address_transactions( $address );
			if ( empty( $address_transactions_result->is_unused() ) ) {
				$actual_unused_addresses_by_wallet[ $address_wallet_id ][] = $address;
			} else {
				$unexpectedly_used_addresses_by_wallet[ $address_wallet_id ][] = $address;

				$this->wallet_service->set_payment_address_status(
					address: $address,
					status: Bitcoin_Address_Status::USED,
				);

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
					$address_transactions_result = $this->payment_service->update_address_transactions( $new_address );

					$is_used_status = $address_transactions_result->is_unused() ? Bitcoin_Address_Status::UNUSED : Bitcoin_Address_Status::USED;

					$this->wallet_service->set_payment_address_status(
						address: $new_address,
						status: $is_used_status,
					);

					if ( empty( $address_transactions_result->queried_address->get_tx_ids() ) ) {
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
		$address_generation_result = $this->wallet_service->generate_new_addresses_for_wallet( $wallet, $generate_count );

		/**
		 * @see self::check_addresses_for_transactions()
		 */
		$this->background_jobs_scheduler->schedule_single_ensure_unused_addresses( $wallet );

		return $address_generation_result;
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

		$addresses = $this->wallet_service->get_unknown_bitcoin_addresses();

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
				$update_result = $this->payment_service->update_address_transactions( $bitcoin_address );

				if ( $bitcoin_address->get_status() === Bitcoin_Address_Status::UNKNOWN ) {
					$this->wallet_service->set_payment_address_status(
						address: $bitcoin_address,
						status: ( 0 === count( $update_result->all_transactions ) ) ? Bitcoin_Address_Status::UNUSED : Bitcoin_Address_Status::USED
					);
				}

				$result[ $bitcoin_address->get_raw_address() ] = $update_result;
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
	 * @see Background_Jobs_Actions_Interface::check_assigned_addresses_for_transactions()
	 * @used-by Background_Jobs_Actions_Handler::check_assigned_addresses_for_transactions()
	 */
	public function check_assigned_addresses_for_payment(): Check_Assigned_Addresses_For_Transactions_Result {

		foreach ( $this->wallet_service->get_assigned_bitcoin_addresses() as $bitcoin_address ) {
			$this->check_address_for_payment( $bitcoin_address );
		}
		// TODO: Return actual result with count of addresses checked.
		return new Check_Assigned_Addresses_For_Transactions_Result( count:0 );
	}


	/**
	 * Check a Bitcoin address for payment and mark as paid if sufficient funds received.
	 *
	 * @param Bitcoin_Address $payment_address The Bitcoin address to check.
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the address being checked has no `target_amount` set.
	 * @throws MoneyMismatchException If the `target_amount` and `total_received` currencies were somehow different.
	 */
	public function check_address_for_payment( Bitcoin_Address $payment_address ): Check_Address_For_Payment_Result {

		// TODO: Maybe throw if the address has not been assigned.

		$target_amount = $payment_address->get_target_amount();
		if ( ! $target_amount ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'No target payment amount on address "' . $payment_address->get_raw_address() . '"' );
		}

		$check_address_for_payment_service_result = $this->payment_service->check_address_for_payment( $payment_address );

		$total_received = $check_address_for_payment_service_result->total_received;

		$is_paid = $total_received->isGreaterThanOrEqualTo( $target_amount );

		if ( $is_paid ) {
			$this->mark_address_as_paid( $payment_address );
		}

		return new Check_Address_For_Payment_Result(
			check_address_for_payment_service_result: $check_address_for_payment_service_result,
			is_paid: $is_paid,
			refreshed_address: $this->wallet_service->refresh( $payment_address ),
		);
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

		$this->wallet_service->set_payment_address_status(
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

		$transaction_post_ids = $this->wallet_service->get_transactions_wp_post_ids_for_address( $bitcoin_address );

		if ( is_null( $transaction_post_ids ) ) {
			return null;
		}

		return $this->payment_service->get_saved_transactions( $transaction_post_ids );
	}
}
