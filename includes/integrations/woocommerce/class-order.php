<?php
/**
 * Constants for order meta-keys.
 *
 * Handles new transactions and payment actions.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Check_Address_For_Payment_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Throwable;
use WC_Order;

/**
 * Defines constants for meta-keys.
 *
 * @see WooCommerce_Integration::define_order_hooks()
 */
class Order implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_WooCommerce_Interface $api_woocommerce Functions for querying/fetching crypto payment details for WooCommerce orders.
	 * @param LoggerInterface           $logger PSR logger.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api_woocommerce,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Record new transactions as order notes.
	 *
	 * @hooked bh_wp_bitcoin_gateway_new_transactions_seen
	 *
	 * @param string|class-string|null                 $integration_id Identifier for the integration the payment address was used by.
	 * @param ?int                                     $order_post_id Identifier for the order the payment address was assigned to.
	 * @param Bitcoin_Address                          $payment_address The address the transactions were found for.
	 * @param Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result The detail of existing and new transactions.
	 */
	public function new_transactions_seen(
		?string $integration_id,
		?int $order_post_id,
		Bitcoin_Address $payment_address,
		Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result,
	): void {

		if ( WooCommerce_Integration::class !== $integration_id ) {
			return;
		}

		try {
			$wc_order = $this->api_woocommerce->get_bitcoin_order( $order_post_id );

			if ( ! $wc_order ) {
				return;
			}

			// TODO: Should also update the confirmed_received amount on the order meta.

			$this->api_woocommerce->add_order_note_for_transactions(
				$wc_order,
				$check_address_for_payment_service_result->get_new_transactions()
			);
		} catch ( Throwable $throwable ) {
			// This runs inside the payment-check loop, the thank-you AJAX and the admin metabox; never abort those.
			$this->logger->error(
				'Error recording new transactions on `shop_order:' . $order_post_id . '`: ' . $throwable->getMessage(),
				array(
					'order_id'  => $order_post_id,
					'address'   => $payment_address->get_raw_address(),
					'exception' => $throwable,
				)
			);
		}
	}

	/**
	 * Mark the order as paid.
	 *
	 * @hooked bh_wp_bitcoin_gateway_payment_received
	 *
	 * @param string|class-string|null                 $integration_id Identifier for the integration the payment address was used by.
	 * @param ?int                                     $order_post_id Identifier for the order the payment address was assigned to.
	 * @param Bitcoin_Address                          $payment_address The address the transactions were found for.
	 * @param Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result The detail of existing and new transactions, amounts.
	 */
	public function payment_received(
		?string $integration_id,
		?int $order_post_id,
		Bitcoin_Address $payment_address,
		Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result,
	): void {

		if ( WooCommerce_Integration::class !== $integration_id ) {
			return;
		}

		try {
			$wc_order = $this->api_woocommerce->get_bitcoin_order( $order_post_id );

			if ( ! $wc_order ) {
				return;
			}

			$this->api_woocommerce->mark_order_paid(
				$wc_order,
				$check_address_for_payment_service_result
			);
		} catch ( Throwable $throwable ) {
			// Marking paid sends emails and fires many third-party hooks; a failure there is logged, not fatal.
			$this->logger->error(
				'Error marking `shop_order:' . $order_post_id . '` paid: ' . $throwable->getMessage(),
				array(
					'order_id'  => $order_post_id,
					'address'   => $payment_address->get_raw_address(),
					'exception' => $throwable,
				)
			);
		}
	}
}
