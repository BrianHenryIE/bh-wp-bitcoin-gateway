<?php
/**
 * Print payment details in customer emails.
 *
 * TODO: Prevent sending the on-hold email immediately, reschedule it for one hour later.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Throwable;
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

		$order_id = $order->get_id();

		try {
			$bitcoin_order = $this->api->get_bitcoin_order( $order_id );

			if ( ! $bitcoin_order ) {
				return;
			}

			$template_args = $this->api->get_formatted_order_details( $bitcoin_order );

			$template_args['template'] = self::TEMPLATE_NAME;

			// TODO: Create a plain text template.
			wc_get_template( self::TEMPLATE_NAME, $template_args );
		} catch ( Throwable $throwable ) {
			// This runs inside WooCommerce's email sending, which itself runs inside `payment_complete()`; a failure
			// here must not stop the email, or the payment processing that triggered it.
			$this->logger->warning(
				"Failed to print `shop_order:{$order_id}` details for Email template: {$throwable->getMessage()}",
				array(
					'order_id'  => $order_id,
					'exception' => $throwable,
				)
			);
		}
	}
}
