<?php
/**
 * Add support for plugin: WooCommerce Cancel Abandoned Order.
 *
 * * Enables the options for Bitcoin gateways
 * * Prevents partially paid orders from being canceled
 *
 * @see https://github.com/rvola/woo-cancel-abandoned-order
 * @see https://wordpress.org/plugins/woo-cancel-abandoned-order/
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order;

use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order_Interface;
use Exception;
use RVOLA\WOO\CAO\CAO;
use WC_Order;

/**
 * Adds additional settings to automatically cancel on-hold orders at
 * `wp-admin/admin.php?page=wc-settings&tab=checkout&section=bitcoin_gateway`.
 */
class Woo_Cancel_Abandoned_Order {

	/**
	 * Constructor.
	 *
	 * @param API_Interface             $api The main plugin functions.
	 * @param API_WooCommerce_Interface $api_woocommerce The WooCommerce related functions.
	 */
	public function __construct(
		protected API_Interface $api,
		protected API_WooCommerce_Interface $api_woocommerce,
	) {
	}

	/**
	 * Enable Woo Cancel Abandoned Order settings for Bitcoin gateway.
	 *
	 * I don't know why it's not just enabled for all gateways, but whatever.
	 *
	 * @hooked woo_cao_gateways
	 * @see CAO::add_field_gateways()
	 *
	 * @param string[] $gateway_ids The set of WooCommerce gateways that Cancel Abandoned Order is enabled for.
	 *
	 * @return string[]
	 */
	public function enable_cao_for_bitcoin( array $gateway_ids ): array {

		foreach ( $this->api_woocommerce->get_bitcoin_gateways() as $bitcoin_gateway ) {
			$gateway_ids[] = $bitcoin_gateway->id;
		}

		return $gateway_ids;
	}

	/**
	 * Before cancelling an old Bitcoin order, make sure to check it is not partially paid.
	 *
	 * Cancel the order if there are no transactions.
	 *
	 * @hooked woo_cao_before_cancel_order
	 * @see CAO::cancel_order()
	 *
	 * @param bool     $should_cancel The already determined decision to cancel, default true.
	 * @param int      $order_id The order id.
	 * @param WC_Order $order The order object itself.
	 *
	 * @return bool Should the order be cancelled?
	 */
	public function abort_canceling_partially_paid_order( bool $should_cancel, int $order_id, WC_Order $order ): bool {

		if ( ! $this->api_woocommerce->is_order_has_bitcoin_gateway( $order_id ) ) {
			return $should_cancel;
		}

		try {
			/** @var WC_Bitcoin_Order_Interface $bitcoin_order */
			$bitcoin_order = $this->api_woocommerce->get_order_details( $order );
		} catch ( Exception $exception ) {
			// If something is going wrong, do not automatically cancel the order.
			return false;
		}

		$address_transaction = $this->api->get_saved_transactions( $bitcoin_order->get_address() );

		return empty( $address_transaction );
	}
}
