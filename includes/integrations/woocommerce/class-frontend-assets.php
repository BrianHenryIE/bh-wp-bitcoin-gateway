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
use Throwable;
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

		try {
			if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
				// Although we're on the thank-you page, this isn't a Bitcoin order.
				return;
			}
		} catch ( Throwable $throwable ) {
			$this->logger->error(
				"Failed to check `shop_order:{$order_id}` gateway when enqueuing styles: {$throwable->getMessage()}",
				array(
					'order_id'  => $order_id,
					'exception' => $throwable,
				)
			);
			return;
		}

		$version = $this->settings->get_plugin_version();
		wp_enqueue_style( 'bh-wp-bitcoin-gateway', $this->settings->get_plugin_url() . 'assets/css/bh-wp-bitcoin-gateway.css', array(), $version, 'all' );

		wp_enqueue_style( 'dashicons' );
	}

	/**
	 * Reduce the formatted order details to the explicit allow-list of keys the thank-you page JavaScript reads,
	 * both for the initial page data and for each AJAX refresh, so the two always agree.
	 *
	 * @see API_WooCommerce_Interface::get_formatted_order_details()
	 * @used-by AJAX::get_order_details()
	 *
	 * @param array<string, mixed> $order_details The full formatted order details.
	 * @param int                  $order_id The order the details are for.
	 *
	 * @return array{btc_address:string, btc_total:string, order_id:string, btc_amount_received:string, status:string, payment_status_key:string, amount_received:string, amount_unconfirmed:string, order_status:string, order_status_formatted:string, last_checked_time_formatted:string}
	 */
	public static function filter_order_details_for_javascript( array $order_details, int $order_id ): array {
		$string = fn( string $key ): string => isset( $order_details[ $key ] ) && is_string( $order_details[ $key ] ) ? $order_details[ $key ] : '';

		return array(
			'btc_address'                 => $string( 'btc_address' ),
			'btc_total'                   => $string( 'btc_total' ),
			'order_id'                    => (string) $order_id,
			'btc_amount_received'         => $string( 'btc_amount_received' ),
			'status'                      => $string( 'payment_status' ),
			'payment_status_key'          => $string( 'payment_status_key' ),
			'amount_received'             => $string( 'btc_amount_received_formatted' ),
			'amount_unconfirmed'          => $string( 'btc_amount_unconfirmed_formatted' ),
			'order_status'                => $string( 'order_status' ),
			'order_status_formatted'      => $string( 'order_status_formatted' ),
			'last_checked_time_formatted' => $string( 'last_checked_time_formatted' ),
		);
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

		try {
			$order = $this->api->get_bitcoin_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$order_details = $this->api->get_formatted_order_details( $order );
		} catch ( Throwable $throwable ) {
			$this->logger->error(
				'Failed to get order details when enqueuing scripts: ' . $throwable->getMessage(),
				array(
					'order_id'  => $order_id,
					'exception' => $throwable,
				)
			);
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

		$filtered_order_details = self::filter_order_details_for_javascript( $order_details, $order->get_id() );

		$order_details_json = wp_json_encode( $filtered_order_details, JSON_PRETTY_PRINT );

		// How the thank-you page asks the server to check the blockchain (and mempool) for the payment.
		// Each poll is a synchronous blockchain API request, so the interval starts at `poll_interval_ms`,
		// doubles after each check up to `poll_max_interval_ms`, and polling stops altogether once
		// `poll_duration_ms` has passed since the page loaded (a note on the page says so). `0` for the initial
		// interval disables polling; the customer can always click "last checked" to refresh.

		/**
		 * @param int $poll_interval_ms Milliseconds before the first check, and the base for doubling. Default one minute.
		 */
		$poll_interval_ms = (int) apply_filters( 'bh_wp_bitcoin_gateway_thank_you_poll_interval_ms', MINUTE_IN_SECONDS * 1000 );

		/**
		 * @param int $poll_max_interval_ms The longest gap between checks. Default ten minutes.
		 */
		$poll_max_interval_ms = (int) apply_filters( 'bh_wp_bitcoin_gateway_thank_you_poll_max_interval_ms', 10 * MINUTE_IN_SECONDS * 1000 );

		/**
		 * @param int $poll_duration_ms How long after page load to keep checking. Default one hour.
		 */
		$poll_duration_ms = (int) apply_filters( 'bh_wp_bitcoin_gateway_thank_you_poll_duration_ms', HOUR_IN_SECONDS * 1000 );

		$ajax_data      = array(
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( self::class ),
			'poll_interval_ms'     => $poll_interval_ms,
			'poll_max_interval_ms' => $poll_max_interval_ms,
			'poll_duration_ms'     => $poll_duration_ms,
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
