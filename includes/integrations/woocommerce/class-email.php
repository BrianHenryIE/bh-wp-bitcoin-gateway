<?php
/**
 * Print payment details in customer emails.
 *
 * TODO: Prevent sending the on-hold email immediately, reschedule it for one hour later.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Load the order details and pass to the email template.
 */
class Email {
	use LoggerAwareTrait;

	const TEMPLATE_NAME = 'emails/email-bitcoin-instructions-status.php';

	/**
	 * Constructor
	 *
	 * @param API_WooCommerce_Interface $api The main plugin functions.
	 * @param LoggerInterface           $logger A PSR logger.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Add payment instructions or payment status (once paid) to the WC emails.
	 *
	 * @hooked woocommerce_email_before_order_table
	 *
	 * @param WC_Order $order The order object the email is being sent for.
	 * @param bool     $sent_to_admin Is this email being sent to an admin, or a customer.
	 * @param bool     $plain_text Is this plain text email ?( !HTML email ).
	 */
	public function print_instructions( WC_Order $order, bool $sent_to_admin, bool $plain_text = false ): void {

		if ( $sent_to_admin ) {
			// TODO: Think about what information should be in admin emails.
			return;
		}

		if ( ! $this->api->is_bitcoin_gateway( $order->get_payment_method() ) ) {
			return;
		}

		/**
		 * There was an error where seemingly the order object being passed to this function is older than the
		 * one saved in `Bitcoin_Gateway::process_payment()` and the meta was not present, so let's refresh.
		 *
		 * @var WC_Order $order
		 */
		$order = wc_get_order( $order->get_id() );

		try {
			$template_args = $this->api->get_formatted_order_details( $order, false );
		} catch ( \Exception $exception ) {
			$this->logger->warning(
				"Failed to get `shop_order:{$order->get_id()}` details for Email template: {$exception->getMessage()}",
				array(
					'order_id'  => $order->get_id(),
					'exception' => $exception,
				)
			);
			return;
		}

		$template_args['template'] = self::TEMPLATE_NAME;

		// TODO: Create a plain text template.
		wc_get_template( self::TEMPLATE_NAME, $template_args );
	}
}
