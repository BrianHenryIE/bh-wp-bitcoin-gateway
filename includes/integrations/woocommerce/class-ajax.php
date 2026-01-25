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
use WC_Order;

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
	 * @hooked wp_ajax_nopriv_bh_wp_bitcoin_gateway_refresh_order_details
	 */
	public function get_order_details(): void {

		if ( ! check_ajax_referer( Frontend_Assets::class, false, false ) ) {
			wp_send_json_error( array( 'message' => 'Bad/no nonce.' ), 400 );
		}

		if ( ! isset( $_POST['order_id'] ) || ! is_numeric( $_POST['order_id'] ) ) {
			wp_send_json_error( array( 'message' => 'No order id provided.' ), 400 );
		}

		$order_id = intval( wp_unslash( $_POST['order_id'] ) );

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			wp_send_json_error( array( 'message' => 'Invalid order id: ' . $order_id ), 400 );
		}

		$this->is_user_authorized( $order );

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

	/**
	 * Check permission to view the order.
	 *
	 * @see \WC_Shortcode_Checkout::guest_should_verify_email()
	 * @see woocommerce/templates/checkout/form-verify-email.php
	 * @see OrderAuthorizationTrait
	 * @see \WC_Shortcode_Checkout::order_received()
	 *
	 * @param WC_Order $order To find is there a user id set on the order.
	 */
	protected function is_user_authorized( WC_Order $order ): void {

		// If the order has no user/customer id, we assume that WooCommerce verified them before displaying the page.
		if ( empty( $order->get_customer_id() ) ) {
			return;
		}

		// Shop admin?
		if ( current_user_can( 'read_private_shop_orders' ) ) {
			return;
		}

		$wp_user = wp_get_current_user();

		// Logged in and owns the order.
		if ( $order->get_customer_id() === $wp_user->ID ) {
			return;
		}

		wp_send_json_error( array( 'message' => 'Logged in user does not own this order.' ), 403 );
	}
}
