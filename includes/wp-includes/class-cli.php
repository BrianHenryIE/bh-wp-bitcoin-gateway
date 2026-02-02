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
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_CLI_Logger\WP_CLI_Logger;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI_Command;

/**
 * Run `wp bh-bitcoin help` for documentation.
 */
class CLI extends WP_CLI_Command {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_Interface      $api The main plugin functions.
	 * @param Settings_Interface $settings The plugin's settings.
	 * @param LoggerInterface    $logger A PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Settings_Interface $settings,
		LoggerInterface $logger
	) {
		parent::__construct();
		$this->setLogger( $logger );
	}

	/**
	 * Generate new addresses.
	 *
	 * TODO: Should be an option to mark it used (i.e. generate it for external use).
	 *
	 * ## OPTIONS
	 *
	 * [--debug=bh-wp-bitcoin-gateway]
	 * : Show detailed progress.
	 *
	 * ## EXAMPLES
	 *
	 *   # Check for new transactions for all gateways.
	 *   $ wp bh-bitcoin generate-new-addresses
	 *
	 *   # Check for new transactions for all gateways and show detailed progress.
	 *   $ wp bh-bitcoin generate-new-addresses --debug=bh-wp-bitcoin-gateway
	 *
	 * @param array<int|string, string> $args Takes no arguments.
	 */
	public function generate_new_addresses( array $args ): void {

		$result = $this->api->generate_new_addresses();
		$this->api->check_new_addresses_for_transactions();

		// TODO: Print a table of new addresses and their status.
		// Print a summary of the table.

		WP_CLI::log( 'Finished generate-new-addresses.' );
		WP_CLI::log( 'Success' );
	}

	/**
	 * Query the blockchain for updates for an address or order.
	 *
	 * See also: `wp post list --post_type=shop_order --post_status=wc-on-hold --meta_key=_payment_gateway --meta_value=bitcoin_gateway --format=ids`.
	 * `wp post list --post_type=shop_order --post_status=wc-on-hold --meta_key=_payment_gateway --meta_value=bitcoin_gateway --format=ids | xargs -0 -d ' ' -I % wp bh-bitcoin check-transactions % --debug=bh-wp-bitcoin-gateway`
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
	 *   $ wp bh-bitcoin check-transactions 0a1b2c3e4f6g7h9
	 *
	 *   # Check for new transactions for the provided order
	 *   $ wp bh-bitcoin check-transactions 123
	 *
	 *   # Check for new transactions for the provided order, showing detailed progress.
	 *   $ wp bh-bitcoin check-transactions 123 --debug=bh-wp-bitcoin-gateway
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
				 * @param int $input_order_id The CLI input, presumed to be an order id.
				 * @var ?Bitcoin_Address $payment_address
				 */
				$payment_address = apply_filters( 'bh_wp_bitcoin_gateway_get_address_for_order', null, $input_order_id );

				if ( is_null( $payment_address ) ) {
					WP_CLI::error( 'Unable to determine payment address from input.' );
				}

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
				$formatted['new_transactions'] = $result->get_new_transactions();
			}

			$formatted['confirmed_received'] = $result->queried_address->get_amount_received();

			WP_CLI\Utils\format_items( $format, $formatted, array_keys( $formatted ) );

			WP_CLI::log( 'Finished update-address.' );

		} catch ( Exception $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}
	}
}
