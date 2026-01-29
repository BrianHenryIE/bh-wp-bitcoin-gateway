<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Enqueue CSS, JS and JSON order details on the order-received page.
 */
class Frontend_Assets {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param API_WooCommerce_Interface $api Check is the order a Bitcoin order; get the order details.
	 * @param Settings_Interface        $settings Get the plugin version for caching.
	 * @param LoggerInterface           $logger A PSR logger.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api,
		protected Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Try to find the order id as `order-received` or `view-order` in the PHP globals.
	 */
	protected function get_order_id_from_globals(): ?int {

		if ( isset( $GLOBALS['order-received'] ) && is_numeric( $GLOBALS['order-received'] ) ) {
			return absint( $GLOBALS['order-received'] );
		}

		if ( isset( $GLOBALS['view-order'] ) && is_numeric( $GLOBALS['view-order'] ) ) {
			return absint( $GLOBALS['view-order'] );
		}

		return null;
	}

	/**
	 * Register the stylesheets for the frontend-facing side of the site.
	 *
	 * @hooked wp_enqueue_scripts
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles(): void {

		$order_id = $this->get_order_id_from_globals();

		if ( ! $order_id ) {
			return;
		}

		if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			// Although we're on the thank-you page, this isn't a Bitcoin order.
			return;
		}

		$version = $this->settings->get_plugin_version();
		wp_enqueue_style( 'bh-wp-bitcoin-gateway', $this->settings->get_plugin_url() . 'assets/css/bh-wp-bitcoin-gateway.css', array(), $version, 'all' );

		wp_enqueue_style( 'dashicons' );
	}

	/**
	 * Register the JavaScript for the frontend-facing side of the site.
	 *
	 * @hooked wp_enqueue_scripts
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts(): void {

		$order_id = $this->get_order_id_from_globals();

		if ( empty( $order_id ) || ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			return;
		}

		/**
		 * We confirmed this is a shop_order in the previous line.
		 *
		 * @var WC_Order $order
		 */
		$order = wc_get_order( $order_id );

		try {
			$order_details = $this->api->get_formatted_order_details( $order );
		} catch ( \Exception $exception ) {
			$this->logger->error( 'Failed to get order details when enqueuing scripts: ' . $exception->getMessage(), array( 'exception' => $exception ) );
			return;
		}

		$version = $this->settings->get_plugin_version();

		$script_url = $this->settings->get_plugin_url() . 'assets/js/frontend/woocommerce/shortcode/thank-you/thank-you.min.js';

		$webpack_manifest_path = $this->settings->get_plugin_dir()
			. '/assets/js/frontend/woocommerce/shortcode/thank-you/thank-you.min.asset.php';

		/** @var array{dependencies?: array<string>, version?:string} $webpack_manifest */
		$webpack_manifest = (array) include $webpack_manifest_path;

		wp_register_script(
			'bh-wp-bitcoin-gateway-shortcode-thank-you',
			$script_url,
			$webpack_manifest['dependencies'] ?? array( 'jquery' ),
			$webpack_manifest['version'] ?? $this->settings->get_plugin_version(),
			array( 'in_footer' => true )
		);

		wp_enqueue_script( 'bh-wp-bitcoin-gateway-shortcode-thank-you' );

		// Filter array to explicit allow-list containing only the required keys for frontend TypeScript.
		$filtered_order_details = array(
			'btc_address'                 => $order_details['btc_address'] ?? '',
			'btc_total'                   => isset( $order_details['btc_total'] ) && ( $order_details['btc_total'] instanceof Money ) ? $order_details['btc_total']->getAmount()->toScale( 8 ) : '',
			'order_id'                    => (string) $order->get_id(),
			'btc_amount_received'         => isset( $order_details['btc_amount_received'] ) && is_string( $order_details['btc_amount_received'] ) ? $order_details['btc_amount_received'] : '',
			'status'                      => isset( $order_details['payment_status'] ) && is_string( $order_details['payment_status'] ) ? $order_details['payment_status'] : '',
			'amount_received'             => isset( $order_details['btc_amount_received_formatted'] ) && is_string( $order_details['btc_amount_received_formatted'] ) ? $order_details['btc_amount_received_formatted'] : '',
			'order_status_formatted'      => isset( $order_details['order_status_formatted'] ) && is_string( $order_details['order_status_formatted'] ) ? $order_details['order_status_formatted'] : '',
			'last_checked_time_formatted' => isset( $order_details['last_checked_time_formatted'] ) && is_string( $order_details['last_checked_time_formatted'] ) ? $order_details['last_checked_time_formatted'] : '',
		);

		$order_details_json = wp_json_encode( $filtered_order_details, JSON_PRETTY_PRINT );

		$ajax_data      = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::class ),
		);
		$ajax_data_json = wp_json_encode( $ajax_data, JSON_PRETTY_PRINT );

		$script = <<<EOD
var bh_wp_bitcoin_gateway_ajax_data = $ajax_data_json;
var bh_wp_bitcoin_gateway_order_details = $order_details_json;
EOD;

		wp_add_inline_script(
		// TODO: move this into WooCommerce specific file, then page specific.
		// 'bh-wp-bitcoin-gateway-shortcode-checkout-thank-you'.
		// 'bh-wp-bitcoin-gateway-woocommerce-thank-you-classic-theme'.
			'bh-wp-bitcoin-gateway-shortcode-thank-you',
			$script,
			'before'
		);
	}
}
