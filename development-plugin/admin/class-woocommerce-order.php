<?php
/**
 * Add a link in the admin order UI to the customer order (thank you/order received) page.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Admin;

use WC_Order;

/**
 * Add JS/CSS on the edit shop_order page.
 */
class WooCommerce_Order {

	/**
	 * Add the action.
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'order_link' ) );
	}

	/**
	 * Determine is the page the admin order view, then get the order id from the URL.
	 *
	 * @see wp-admin/admin.php?page=wc-orders&action=edit&id=71
	 */
	protected function get_woocommerce_admin_order_page_order_id(): ?int {

		/** @var string $pagenow */
		global $pagenow;

		if ( 'post.php' === $pagenow && isset( $_GET['post'] ) && is_string( $_GET['post'] ) ) {
			$post_id   = absint( $_GET['post'] );
			$post_type = get_post_type( $post_id );
			if ( 'shop_order' === $post_type ) {
				return $post_id;
			}
		}
		if ( 'admin.php' === $pagenow && isset( $_GET['id'] ) && is_string( $_GET['id'] )
			&& isset( $_GET['page'] ) && 'wc-orders' === $_GET['page'] ) {
			$post_id = absint( $_GET['id'] );
			return $post_id;
		}

		return null;
	}

	/**
	 * Add a "Customer Order Link" to the order admin page.
	 *
	 * @hooked admin_footer
	 */
	public function order_link(): void {

		$order_id = $this->get_woocommerce_admin_order_page_order_id();
		if ( ! $order_id ) {
			return;
		}

		/** @var WC_Order $wc_order */
		$wc_order = wc_get_order( $order_id );
		$link     = $wc_order->get_checkout_order_received_url();

		echo '<script>';
		printf(
			"jQuery('.woocommerce-order-data__heading').append('<span style=\"display: inline-block;\"><a class=\"customer_order_link\" title=\"Customer order link\" target=\"_blank\" href=\"%s\">Customer Order Link</a></span>')",
			esc_url( $link )
		);
		echo '</script>';

		echo '<style>';
		echo <<<EOT
			.customer_order_link {
			  color: #333; margin: 1.33em 0 0;
			  width: 14px;
			  height: 0;
			  padding: 14px 0 0;
			  margin: 0 0 0 6px;
			  overflow: hidden;
			  position: relative;
			  color: #999;
			  border: 0;
			  float: right;
			}
			.customer_order_link::after {
			  font-family: Dashicons;
			  content: "\\f504";
			  position: absolute;
			  top: 0;
			  left: 0;
			  text-align: center;
			  vertical-align: top;
			  line-height: 14px;
			  font-size: 14px;
			  font-weight: 400;
			}
			EOT;
		echo '</style>';
	}
}
