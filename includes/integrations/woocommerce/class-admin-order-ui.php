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

		/** @var WP_Post $post */
		global $post;

		if ( 'shop_order' !== $post->post_type ) {
			return;
		}

		$order_id = $post->ID;

		if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			return;
		}

		add_meta_box(
			'bh-wp-bitcoin-gateway',
			'Bitcoin',
			array( $this, 'print_address_transactions_metabox' ),
			'shop_order',
			'normal',
			'core'
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
	 * @param WP_Post $post The post this edit page is running for.
	 */
	public function print_address_transactions_metabox( WP_Post $post ): void {

		$order_id = $post->ID;

		if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			return;
		}

		/**
		 * This is almost sure to be a valid order object, since this only runs on the order page.
		 *
		 * @var WC_Order $order
		 */
		$order = wc_get_order( $order_id );

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
