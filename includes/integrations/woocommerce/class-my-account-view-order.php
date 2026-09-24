<?php
/**
 * Add the payment instructions to the order page.
 *
 * @see woocommerce/templates/myaccount/view-order.php
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Throwable;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Get the order details and pass them to the my-account order ui template.
 */
class My_Account_View_Order {
	use LoggerAwareTrait;

	const string TEMPLATE_NAME = 'myaccount/view-order-bitcoin-instructions-status.php';

	/**
	 * Constructor
	 *
	 * @param API_WooCommerce_Interface $api Check is it a Bitcoin order; get the order details.
	 * @param LoggerInterface           $logger A PSR logger.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * If it is a Bitcoin order being displayed, print the payment details, via template.
	 *
	 * @hooked woocommerce_view_order
	 *
	 * @param int $order_id The id of the order being viewed.
	 */
	public function print_status_instructions( int $order_id ): void {

		try {
			$order = $this->api->get_bitcoin_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$template_args = $this->api->get_formatted_order_details( $order );

			$template_args['template'] = self::TEMPLATE_NAME;

			wc_get_template( self::TEMPLATE_NAME, $template_args );
		} catch ( Throwable $throwable ) {
			// Typically an order with no Bitcoin address, e.g. if there was a problem fetching one as the order was
			// created; whatever the cause, never break the my-account page.
			$this->logger->warning(
				"Failed to print `shop_order:{$order_id}` details for my-account template: {$throwable->getMessage()}",
				array(
					'order_id'  => $order_id,
					'exception' => $throwable,
				)
			);
		}
	}
}
