<?php
/**
 * WP CLI commands for invoking API functions.
 *
 * Most useful to check an order for payment without waiting for Action Scheduler.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Helpers\WC_Order_Meta_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_CLI_Logger\WP_CLI_Logger;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WC_Order;
use WC_Payment_Gateway;
use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI_Command;

/**
 * Run `wp bh_bitcoin help` for documentation.
 *
 * `wp post list --post_type=bh-bitcoin-wallet`.
 */
class CLI {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_Interface              $api The main plugin functions.
	 * @param API_WooCommerce            $api_woocommerce WooCommerce functions.
	 * @param Settings_Interface         $settings The plugin's settings.
	 * @param Bitcoin_Address_Repository $bitcoin_address_repository For refreshing addresses.
	 * @param LoggerInterface            $logger A PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected API_WooCommerce $api_woocommerce,
		protected Settings_Interface $settings,
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * @hooked cli_init
	 */
	public function register_commands(): void {
		try {
			WP_CLI::add_command( 'bh_bitcoin generate_new_addresses', array( $this, 'generate_new_addresses' ) );
			WP_CLI::add_command( 'bh_bitcoin check_transactions', array( $this, 'check_transactions' ) );
			WP_CLI::add_command( 'bh_bitcoin gateway list', array( $this, 'gateway_list' ) );
			WP_CLI::add_command( 'bh_bitcoin order list', array( $this, 'order_list' ) );
		} catch ( Exception $e ) {
			$this->logger->error( 'Failed to register WP CLI commands: ' . $e->getMessage(), array( 'exception' => $e ) );
		}
	}

	/**
	 * Generate new addresses.
	 *
	 * TODO: Should be an option to mark it used (i.e. generate it for external use).
	 *
	 * ## OPTIONS
	 *
	 *  [--debug=<bh-wp-bitcoin-gateway>]
	 *  : Show detailed progress.
	 *
	 * ## EXAMPLES
	 *
	 *   # Check for new transactions for all gateways.
	 *   $ wp bh_bitcoin generate_new_addresses
	 *
	 *   # Check for new transactions for all gateways and show detailed progress.
	 *   $ wp bh_bitcoin generate_new_addresses --debug=bh-wp-bitcoin-gateway
	 *
	 * @param array<int|string, string> $args Takes no arguments.
	 */
	public function generate_new_addresses( array $args ): void {

		$new_addresses = $this->api->generate_new_addresses();

		$transactions = $this->api->check_new_addresses_for_transactions();

		/**
		 * Wallets indexed by post_id.
		 *
		 * @var array<int, Bitcoin_Wallet> $wallets
		 */
		$wallets = array();

		$wallets_display = array();

		foreach ( $new_addresses as $result ) {
			$wallets[ $result->wallet->get_post_id() ] = $result->wallet;
			$wallets_display[]                         = array(
				'master_public_key'   => substr( $result->wallet->get_xpub(), 0, 7 ) . '...' . substr( $result->wallet->get_xpub(), - 3, 3 ),
				'post_id'             => $result->wallet->get_post_id(),
				'prior_address_index' => $result->prior_address_index ?? 0,
				'new_address_index'   => $result->get_highest_address_index(),
				'generated_count'     => count( $result->new_addresses ),
				'orphaned_count'      => count( $result->orphaned_addresses ),
			);
		}

		if ( empty( $wallets ) ) {
			WP_CLI::log( 'No wallets found.' );
			return;
		}

		WP_CLI\Utils\format_items( 'table', $wallets_display, array_keys( $wallets_display[0] ) );

		/** @var array<array<string,string>> $addresses_display */
		$addresses_display = array();
		foreach ( $new_addresses as $result ) {
			foreach ( $result->new_addresses as $address ) {

				// Refresh after running `::check_new_addresses_for_transactions()`.
				$address = $this->bitcoin_address_repository->refresh( $address );

				$wallet_master_public_key = $wallets[ $address->get_wallet_parent_post_id() ]->get_xpub();

				$addresses_display[] = array(
					'post_id'    => (string) $address->get_post_id(),
					'address'    => $address->get_raw_address(),
					'wallet'     => substr( $wallet_master_public_key, 0, 7 ) . '...' . substr( $wallet_master_public_key, - 3, 3 ),
					'derivation' => (string) $address->get_derivation_path_sequence_number(),
					'status'     => $address->get_status()->value,
				);
			}
		}

		if ( ! empty( $addresses_display ) ) {
			WP_CLI::log( 'New addresses:' );
			WP_CLI\Utils\format_items( 'table', array_values( $addresses_display ), array_keys( $addresses_display[0] ) );
		} else {
			WP_CLI::log( 'No new addresses.' );
		}

		WP_CLI::log( 'Finished generate_new_addresses.' );
	}

	/**
	 * Query the blockchain for updates for an address or order.
	 *
	 * See also: `wp post list --post_type=shop_order --post_status=wc-on-hold --meta_key=_payment_gateway --meta_value=bitcoin_gateway --format=ids`.
	 * `wp post list --post_type=shop_order --post_status=wc-on-hold --meta_key=_payment_gateway --meta_value=bitcoin_gateway --format=ids | xargs -0 -d ' ' -I % wp bh_bitcoin check_transactions % --debug=bh-wp-bitcoin-gateway`
	 *
	 *
	 * ## OPTIONS
	 *
	 * <input>
	 * : A payment address string, payment address post_id, or order id.
	 *
	 * [--format=<format>]
	 * Render output in a specific format.
	 * ---
	 * default: table
	 * options:
	 * - table
	 * - json
	 * - csv
	 * - yaml
	 * ---
	 *
	 * [--debug=<bh-wp-bitcoin-gateway>]
	 * : Show detailed progress.
	 *
	 * ## EXAMPLES
	 *
	 *   # Check for new transactions for the provided Bitcoin address
	 *   $ wp bh_bitcoin check_transactions 0a1b2c3e4f6g7h9
	 *
	 *   # Check for new transactions for the provided order
	 *   $ wp bh_bitcoin check_transactions 123
	 *
	 *   # Check for new transactions for the provided order, showing detailed progress.
	 *   $ wp bh_bitcoin check_transactions 123 --debug=bh-wp-bitcoin-gateway
	 *
	 * @param string[]             $args The address.
	 * @param array<string,string> $assoc_args List of named arguments.
	 *
	 * @throws ExitException When given input that does not match a known xpub, or post_id for a bitcoin address or relevant WooCommerce order.
	 * @throws InvalidArgumentException When the input does not match an existing object.
	 */
	public function check_transactions( array $args, array $assoc_args ): void {

		/** @var string $input */
		$input  = $args[0];
		$format = $assoc_args['format'] ?? 'table';

		// TODO: use BH WP_CLI_Logger here.
		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build(), new NullLogger() );
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		try {
			if ( ! is_numeric( $input ) ) {
				// Assuming a raw address has been input.
				$bitcoin_address_post_id = $bitcoin_address_repository->get_post_id_for_address( $input );
				if ( is_null( $bitcoin_address_post_id ) ) {
					$this->logger->error( 'Could not find Bitcoin address object for {input}.', array( 'input' => $input ) );
					WP_CLI::error(
						sprintf(
							'Could not find Bitcoin address object for %s.',
							$input
						)
					);
				}
				try {
					$payment_address = $bitcoin_address_repository->get_by_post_id( $bitcoin_address_post_id );
				} catch ( Exception $exception ) {
					$this->logger->error(
						'Could not find Bitcoin address object for {input}.',
						array(
							'exception' => $exception,
							'input'     => $input,
						)
					);
					WP_CLI::error(
						sprintf(
							'Could not find Bitcoin address object for %s.',
							$input
						)
					);
				}
			} elseif ( Bitcoin_Address_WP_Post_Interface::POST_TYPE === get_post_type( intval( $input ) ) ) {
				$this->logger->debug( 'CLI input was `bh-bitcoin-address:{input}`', array( 'input' => $input ) );
				$payment_address = $bitcoin_address_factory->get_by_wp_post_id( intval( $input ) );

			} else {

				$input_order_id = intval( $input );
				/**
				 * @param ?Bitcoin_Address $payment_address
				 * @param int $input_order_id The CLI input, presumed to be an order id.
				 */
				$payment_address = apply_filters( 'bh_wp_bitcoin_gateway_get_address_for_order', null, $input_order_id );

				if ( is_null( $payment_address ) ) {
					WP_CLI::error( 'Unable to determine payment address from input.' );
				}

				/** @phpstan-ignore-next-line instanceof.alwaysTrue (The filter really returns mixed) */
				if ( ! ( $payment_address instanceof Bitcoin_Address ) ) {
					WP_CLI::error( 'Invalid value returned from filter.' );
				}
			}

			$result = $this->api->check_address_for_payment( $payment_address );

			$is_updated = $result->is_updated();

			$formatted = array(
				'address' => $result->queried_address->get_raw_address(),
				'updated' => wc_bool_to_string( $is_updated ),
			);

			if ( $is_updated ) {
				$formatted['new_transactions'] = implode(
					',',
					array_map(
						fn( Transaction_Interface $transaction ) => $transaction->get_txid(),
						$result->get_new_transactions()
					)
				);
			}

			$formatted['confirmed_received'] = (string) $result->queried_address->get_amount_received();

			// This only ever prints one line.
			WP_CLI\Utils\format_items( $format, array( $formatted ), array_keys( $formatted ) );

			WP_CLI::log( 'Finished update-address.' );

		} catch ( Exception $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}

	/**
	 * List gateway instances.
	 *
	 * See also:
	 * `wp wc payment_gateway list --user=admin`
	 * `wp wc payment_gateway get bh_bitcoin --user=admin`
	 * `wp option get woocommerce_bh_bitcoin_settings`
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * Render output in a specific format.
	 * ---
	 * default: table
	 * options:
	 * - table
	 * - json
	 * - csv
	 * - yaml
	 * ---
	 *
	 * [--debug=<bh-wp-bitcoin-gateway>]
	 * : Show detailed progress.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp bh_bitcoin gateway list
	 *
	 * @param string[]             $_args No positional arguments.
	 * @param array<string,string> $assoc_args List of named arguments.
	 */
	public function gateway_list( array $_args, array $assoc_args ): void {

		$format = $assoc_args['format'] ?? 'table';

		$bitcoin_gateways = array();

		if ( function_exists( 'WC' ) ) {
			$all_woocommerce_gateways = WC()->payment_gateways()->payment_gateways();

			$woocommerce_bitcoin_gateways = array_filter(
				$all_woocommerce_gateways,
				fn( WC_Payment_Gateway $gateway ) => $gateway instanceof Bitcoin_Gateway
			);

			$woocommerce_bitcoin_gateways_info = array_map(
				function ( Bitcoin_Gateway $gateway ) {
					// TODO: `order_count`.

					$master_public_key = $gateway->get_xpub() ?? '';
					return array(
						'integration'  => 'woocommerce',
						'id'           => $gateway->id,
						'is_available' => $gateway->is_available() ? 'yes' : 'no',
						'xpub'         => substr( $master_public_key, 0, 7 ) . '...' . substr( $master_public_key, - 3, 3 ),
					);
				},
				$woocommerce_bitcoin_gateways
			);

			$bitcoin_gateways = array_merge( $bitcoin_gateways, array_values( $woocommerce_bitcoin_gateways_info ) );
		} else {
			WP_CLI::log( 'WooCommerce is not active.' );
		}

		if ( empty( $bitcoin_gateways ) ) {
			WP_CLI::log( 'No Bitcoin gateway found.' );
		} else {
			WP_CLI\Utils\format_items( $format, $bitcoin_gateways, array_keys( $bitcoin_gateways[0] ) );
		}
	}

	/**
	 * List orders using Bitcoin gateway.
	 *
	 * See also:
	 * `wp wc shop_order list --user=admin`
	 * `wp wc shop_order get {order_id} --user=admin`
	 * `wp wc shop_order get {order_id} --field=meta_data --user=admin`
	 *
	 * ## OPTIONS
	 *
	 *  [--status=<status>]
	 *  : Filter by order status.
	 *  ---
	 *  default: all
	 *  options:
	 *  - pending
	 *  - on-hold
	 *  - processing
	 *  - etc.
	 *  ---
	 *
	 *  [--format=<format>]
	 *  Render output in a specific format.
	 *  ---
	 *  default: table
	 *  options:
	 *  - table
	 *  - json
	 *  - csv
	 *  - yaml
	 *  ---
	 *
	 *  [--debug=<bh-wp-bitcoin-gateway>]
	 *  : Show detailed progress.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp bh_bitcoin order list
	 *
	 * @param string[]             $_args No positional arguments.
	 * @param array<string,string> $assoc_args List of named arguments.
	 */
	public function order_list( array $_args, array $assoc_args ): void {

		$format = $assoc_args['format'] ?? 'table';
		$status = $assoc_args['status'] ?? null;

		$all_orders_info = array();

		if ( function_exists( 'WC' ) ) {
			$all_woocommerce_gateways = WC()->payment_gateways()->payment_gateways();

			$woocommerce_bitcoin_gateways = array_filter(
				$all_woocommerce_gateways,
				fn( WC_Payment_Gateway $gateway ) => $gateway instanceof Bitcoin_Gateway
			);

			$order_meta_helper = new WC_Order_Meta_Helper( new JsonMapper_Helper()->build() );
			foreach ( $woocommerce_bitcoin_gateways as $bitcoin_gateway ) {
				$args = array(
					'payment_method' => $bitcoin_gateway->id,
					'limit'          => -1,
				);
				if ( $status && 'all' !== $status ) {
					$args['status'] = $status;
				}
				/** @var WC_Order[] $gateway_orders */
				$gateway_orders = wc_get_orders( $args );

				$gateway_orders_info = array_map(
					function ( WC_Order $order ) use ( $order_meta_helper ): array {

						$order_info = array(
							'order_id'                  => $order->get_order_number(),
							'status'                    => $order->get_status(),
							'total'                     => $order->get_currency() . ' ' . $order->get_total(),
							'total_btc'                 => (string) ( $order_meta_helper->get_btc_total_price( $order ) ?? 'error' ),
							'btc_exchange_rate'         => (string) ( $order_meta_helper->get_exchange_rate( $order ) ?? 'error' ),
							'payment_address'           => $order_meta_helper->get_raw_payment_address( $order ) ?? 'error',
							'confirmed_amount_received' => (string) ( $order_meta_helper->get_confirmed_amount_received( $order ) ?? 'error' ),
						);

						try {
							$bitcoin_order = $this->api_woocommerce->get_order_details( $order );

							$order_info['payment_address_post_id'] = (string) $bitcoin_order->get_address()->get_post_id();
							$order_info['last_checked_time']       = $bitcoin_order->get_last_checked_time()?->format( DateTimeInterface::ATOM ) ?? 'never';
						} catch ( Exception $exception ) {
							$this->logger->error( $exception->getMessage(), array( 'exception' => $exception ) );

							$order_info['payment_address_post_id'] = '';
							$order_info['last_checked_time']       = '';
						}

						return $order_info;
					},
					$gateway_orders
				);

				$all_orders_info = array_merge( $all_orders_info, $gateway_orders_info );
			}
		} else {
			WP_CLI::log( 'WooCommerce is not active.' );
		}

		if ( empty( $all_orders_info ) ) {
			WP_CLI::log( 'No Bitcoin orders found.' );
		} else {
			WP_CLI\Utils\format_items( $format, array_values( $all_orders_info ), array_keys( $all_orders_info[0] ) );
		}
	}
}
