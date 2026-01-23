<?php
/**
 * AJAX endpoint for fetching order information.
 *
 * TODO: Move this to /integrations/woocommerce.
 *
 * Used on Thank You and my-account screens to query for transaction updates.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Action `bh_wp_bitcoin_gateway_refresh_order_details` hooked to `wp_ajax` and `wp_ajax_nopriv`.
 */
class AJAX {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param API_WooCommerce_Interface $api The main plugin functions, to get order information.
	 * @param LoggerInterface           $logger A PSR logger.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Return data for number of confirmations,
	 * is the order paid.
	 * does more need to be sent
	 *
	 * @hooked wp_ajax_bh_wp_bitcoin_gateway_refresh_order_details
	 *
	 * @return void
	 */
	public function get_order_details() {

		if ( ! check_ajax_referer( Frontend_Assets::class, false, false ) ) {
			wp_send_json_error( array( 'message' => 'Bad/no nonce.' ), 400 );
		}

		if ( ! isset( $_POST['order_id'] ) || ! is_numeric( $_POST['order_id'] ) ) {
			wp_send_json_error( array( 'message' => 'No order id provided.' ), 400 );
		}

		$order_id = intval( wp_unslash( $_POST['order_id'] ) );

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof \WC_Order ) ) {
			wp_send_json_error( array( 'message' => 'Invalid order id: ' . $order_id ), 400 );
		}

		// TODO: Include the order key in the AJAX request.
		// Check `$order->get_customer_id() !== get_current_user_id()` and `$order->key_is_valid( $key )`.

		$result = $this->api->get_formatted_order_details( $order, true );

		// These are the only keys used by the JavaScript.
		$allowed_keys = array(
			'btc_address',
			'btc_total',
			'order_id',
			'btc_amount_received',
			'status',
			'amount_received',
			'order_status_formatted',
			'last_checked_time_formatted',
		);

		foreach ( array_keys( $result ) as $key ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				unset( $result[ $key ] );
			}
		}

		wp_send_json_success( $result );
	}
}
