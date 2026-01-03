<?php
/**
 * Add a metabox with the payment details on the admin order page.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;
use WP_Post;

/**
 * Register and print a metabox on the shop_order page, display it only when the order is a Bitcoin order.
 */
class Admin_Order_UI {
	use LoggerAwareTrait;

	const TEMPLATE_NAME = 'admin/single-order-ui-bitcoin-details-metabox.php';

	/**
	 * Constructor
	 *
	 * @param API_WooCommerce_Interface $api Instance of the main plugin class. Required for order details.
	 * @param LoggerInterface           $logger PSR logger.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Register the Bitcoin order details metabox on shop_order admin edit view.
	 *
	 * @hooked add_meta_boxes
	 *
	 * @return void
	 */
	public function register_address_transactions_meta_box(): void {

		$order_id = null;

		/** @var ?WP_Post $post */
		global $post;

		/** @var ?string $pagenow */
		global $pagenow; // admin.php

		/** @var ?string $plugin_page */
		global $plugin_page; // wc-orders

		if ( ( $post instanceof WP_Post ) && 'shop_order' === $post->post_type ) {
			$order_id = $post->ID;
		}

		if ( 'admin.php' === $pagenow && 'wc-orders' === $plugin_page ) {
			$order_id = absint( $_GET['id'] );
		}

		if ( is_null( $order_id ) ) {
			return;
		}

		if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			return;
		}

		// woocommerce_page_wc-orders
		$screen = function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		// This still didn't work for HPOS.
		add_meta_box(
			'bh-wp-bitcoin-gateway',
			'Bitcoin', // TODO: translate.
			array( $this, 'print_address_transactions_metabox' ),
			$screen, // shop_order or woocommerce_page_wc depending on hpos
			// 'normal',
			// 'core'
		);
	}

	/**
	 * Print a box of information showing the Bitcoin address, amount expcted, paid, transactions, last checked date.
	 *
	 * TODO: Display the difference between amount required and amount paid?
	 * TODO: "Check now" button.
	 *
	 * @see Admin_Order_UI::register_address_transactions_meta_box();
	 *
	 * @param WP_Post|WC_Order $post The post this edit page is running for.
	 */
	public function print_address_transactions_metabox( $post ): void {

		/**
		 * This is almost sure to be a valid order object, since this only runs on the order page.
		 *
		 * @var WC_Order $order
		 */
		$order = $post instanceof WP_Post ? wc_get_order( $post->ID ) : $post;

		$order_id = $order->get_id();

		if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			return;
		}

		// Once the order has been paid, no longer poll for new transactions, unless manually pressing refresh.
		$refresh = ! $order->is_paid();

		try {
			$template_args = $this->api->get_formatted_order_details( $order, $refresh );
		} catch ( \Exception $exception ) {
			$this->logger->warning(
				"Failed to get `shop_order:{$order_id}` details for admin order ui metabox template: {$exception->getMessage()}",
				array(
					'order_id'  => $order_id,
					'exception' => $exception,
				)
			);
			return;
		}

		$template_args['template'] = self::TEMPLATE_NAME;

		wc_get_template( self::TEMPLATE_NAME, $template_args );
	}
}
