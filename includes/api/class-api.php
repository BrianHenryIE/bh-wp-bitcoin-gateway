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
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduling_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\BigDecimal;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\RoundingMode;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Trait;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class API implements API_Interface, API_Background_Jobs_Interface, API_WooCommerce_Interface {
	use LoggerAwareTrait;
	use API_WooCommerce_Trait;

	/**
	 * @var Background_Jobs Object to schedule background jobs.
	 */
	protected Background_Jobs $background_jobs;

	/**
	 * Constructor
	 *
	 * @param Settings_Interface             $settings The plugin settings.
	 * @param LoggerInterface                $logger A PSR logger.
	 * @param Bitcoin_Wallet_Factory         $bitcoin_wallet_factory Wallet repository.
	 * @param Bitcoin_Address_Repository     $bitcoin_address_repository Repository to save and fetch addresses from wp_posts.
	 * @param Blockchain_API_Interface       $blockchain_api The object/client to query the blockchain for transactions.
	 * @param Generate_Address_API_Interface $generate_address_api Object that does the maths to generate new addresses for a wallet.
	 * @param Exchange_Rate_API_Interface    $exchange_rate_api Object/client to fetch the exchange rate.
	 */
	public function __construct(
		protected Settings_Interface $settings,
		LoggerInterface $logger,
		protected Bitcoin_Wallet_Factory $bitcoin_wallet_factory,
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
		protected Blockchain_API_Interface $blockchain_api,
		protected Generate_Address_API_Interface $generate_address_api,
		protected Exchange_Rate_API_Interface $exchange_rate_api,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Set the background jobs scheduler.
	 *
	 * This is not in the constructor to avoid an infinite loop/recursive dependency.
	 *
	 * @param Background_Jobs $background_jobs The background jobs scheduler.
	 */
	public function set_background_jobs( Background_Jobs $background_jobs ): void {
		$this->background_jobs = $background_jobs;
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
	 * Rounds to ~6 decimal places.
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
	 * @return Wallet_Generation_Result
	 * @throws Exception
	 */
	public function generate_new_wallet( string $master_public_key, ?string $gateway_id = null ): Wallet_Generation_Result {

		$post_id = $this->bitcoin_wallet_factory->get_post_id_for_wallet( $master_public_key )
			?? $this->bitcoin_wallet_factory->save_new( $master_public_key, $gateway_id );

		$wallet = $this->bitcoin_wallet_factory->get_by_post_id( $post_id );

		$existing_fresh_addresses = $wallet->get_fresh_addresses();

		$generated_addresses = array();

		$count = count( $wallet->get_fresh_addresses() );
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
	 * @see API_Interface::generate_new_addresses()
	 * @used-by CLI::generate_new_addresses()
	 * @used-by Background_Jobs::generate_new_addresses()
	 *
	 * @return Addresses_Generation_Result[]
	 */
	public function generate_new_addresses(): array {

		/**
		 * @var array<int, Addresses_Generation_Result> $results
		 */
		$results = array();

		foreach ( $this->get_bitcoin_gateways() as $gateway ) {
			$gateway_master_public_key = $gateway->get_xpub();
			$gateway_wallet_post_id    = $this->bitcoin_wallet_factory->get_post_id_for_wallet( $gateway_master_public_key );
			if ( is_null( $gateway_wallet_post_id ) ) {
				$gateway_wallet_post_id = $this->bitcoin_wallet_factory->save_new( $gateway_master_public_key, $gateway->id );
			}
			$wallet = $this->bitcoin_wallet_factory->get_by_post_id( $gateway_wallet_post_id );

			$results[] = $this->generate_new_addresses_for_wallet( $wallet );
		}

		return $results;
	}

	/**
	 * @param Bitcoin_Wallet $wallet
	 * @param int            $generate_count // TODO:  20 is the standard lookahead for wallets. cite.
	 *
	 * @throws Exception When no wallet object is found for the master public key (xpub) string.
	 */
	public function generate_new_addresses_for_wallet( Bitcoin_Wallet $wallet, int $generate_count = 20 ): Addresses_Generation_Result {

		$address_index = $wallet->get_address_index();

		$generated_addresses_post_ids = array();
		$generated_addresses_count    = 0;

		do {
			// TODO: Post increment or we will never generate address 0 like this.
			++$address_index;

			$new_address_string = $this->generate_address_api->generate_address( $wallet->get_xpub(), $address_index );

			if ( ! is_null( $this->bitcoin_address_repository->get_post_id_for_address( $new_address_string ) ) ) {
				continue;
			}

			$bitcoin_address_new_post_id = $this->bitcoin_address_repository->save_new( $new_address_string, $address_index, $wallet );

			$generated_addresses_post_ids[] = $bitcoin_address_new_post_id;
			++$generated_addresses_count;

		} while ( $generated_addresses_count < $generate_count );

		$generated_addresses = array_map(
			function ( int $post_id ): Bitcoin_Address {
				return $this->bitcoin_address_repository->get_by_post_id( $post_id );
			},
			$generated_addresses_post_ids
		);

		$wallet->set_address_index( $address_index );

		if ( $generate_count > 0 ) {
			// Check the new addresses for transactions etc.
			$this->check_new_addresses_for_transactions();

			// Schedule more generation after it determines how many unused addresses are available.
			if ( count( $wallet->get_fresh_addresses() ) < 20 ) {
				$this->background_jobs->schedule_generate_new_addresses();
			}
		}

		return new Addresses_Generation_Result(
			$wallet,
			$generated_addresses,
			$address_index,
		);
	}

	/**
	 * @used-by Background_Jobs::check_new_addresses_for_transactions()
	 *
	 * @throws Rate_Limit_Exception
	 *
	 * @return Check_Assigned_Addresses_For_Transactions_Result (was: array<string, array<string, Transaction_Interface>>)
	 */
	public function check_new_addresses_for_transactions(): Check_Assigned_Addresses_For_Transactions_Result {

		// 'orderby'        => 'ID',
		// 'order'          => 'ASC',
		$addresses = $this->bitcoin_address_repository->get_unknown_bitcoin_addresses();

		if ( empty( $addresses ) ) {
			$this->logger->debug( 'No addresses with "unknown" status to check' );

			return new Check_Assigned_Addresses_For_Transactions_Result(); // TODO: return something meaningful.
		}

		return $this->check_addresses_for_transactions( $addresses );
	}

	/**
	 * @used-by Background_Jobs::check_new_addresses_for_transactions()
	 *
	 * @param Bitcoin_Address[] $addresses Array of address objects to query and update.
	 *
	 * @return Check_Assigned_Addresses_For_Transactions_Result (was array<string, array<string, Transaction_Interface>>))
	 */
	public function check_addresses_for_transactions( array $addresses ): Check_Assigned_Addresses_For_Transactions_Result {

		$result = array();

		try {
			foreach ( $addresses as $bitcoin_address ) {
				$result[ $bitcoin_address->get_raw_address() ] = $this->update_address_transactions( $bitcoin_address );
			}
		} catch ( Rate_Limit_Exception $exception ) {
			throw $exception;
		} catch ( Exception $exception ) {
			// Reschedule if we hit 429 (there will always be at least one address to check if it 429s.).
			$this->logger->debug( $exception->getMessage() );

			// Eh.
			$this->background_jobs->schedule_check_newly_generated_bitcoin_addresses_for_transactions(
				DateTimeImmutable::createFromFormat( 'U', (string) ( time() + ( 15 * ( (int) constant( 'MINUTE_IN_SECONDS' ) ) ) ) ),
			);
		}

		// TODO: After this is complete, there could be 0 fresh addresses (e.g. if we start at index 0 but 200 addresses
		// are already used). => We really need to generate new addresses until we have some.

		// TODO: Return something useful.
		return new Check_Assigned_Addresses_For_Transactions_Result();
	}

	/**
	 * Remotely check/fetch the latest data for an address.
	 *
	 * @param Bitcoin_Address $address The address object to query.
	 *
	 * @return array<string, Transaction_Interface>
	 */
	public function update_address_transactions( Bitcoin_Address $address ): array {

		$btc_xpub_address_string = $address->get_raw_address();
		// TODO: retry on rate limit.
		try {
			$updated_transactions = $this->blockchain_api->get_transactions_received( $btc_xpub_address_string );
			$address->set_transactions( $updated_transactions );
			return $updated_transactions;
		} catch ( Exception $_exception ) {
			// API is offline.
			// TODO: log, rate limit, notify.
			// TODO: is empty array ok here?
			return $address->get_blockchain_transactions() ?? array();
		}

		// TODO: This should be in the API class.
		// if ( $is_paid ) {
		//
		// each address has one parent post (order)
		//
		// $order_post_id = $address_post->post_parent;
		// do_action( 'bh_wp_bitcoin_gateway_payment_received', $address_post_id, $order_post_id, $order_post_type );
		//
		// }
	}

	/**
	 * TODO: The return value should be a structured summary that can be used in a log message.
	 *
	 * @see Background_Jobs_Actions_Interface::check_assigned_addresses_for_transactions()
	 * @used-by Background_Jobs::check_assigned_addresses_for_transactions()
	 */
	public function check_assigned_addresses_for_transactions(): Check_Assigned_Addresses_For_Transactions_Result {

		foreach ( $this->bitcoin_address_repository->get_assigned_bitcoin_addresses() as $bitcoin_address ) {
			$this->update_address_transactions( $bitcoin_address );
		}
		return new Check_Assigned_Addresses_For_Transactions_Result();
	}
}
