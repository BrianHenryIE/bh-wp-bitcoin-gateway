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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Defines constants for meta-keys.
 *
 * @see WooCommerce_Integration::define_order_hooks()
 */
class Order implements LoggerAwareInterface {
	use LoggerAwareTrait;

	const string BITCOIN_ADDRESS_META_KEY = 'bh_wp_bitcoin_gateway_payment_address';

	const string EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_exchange_rate_at_time_of_purchase';

	const string ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_total_at_time_of_purchase';

	const string BITCOIN_AMOUNT_RECEIVED_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_amount_received';

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
	 * Check hooked data is relevant / can return a WC_Order.
	 *
	 * @param ?string $integration_id The plugin integration.
	 * @param ?int    $order_post_id The order post_id.
	 */
	protected function get_wc_order(
		?string $integration_id,
		?int $order_post_id,
	): ?WC_Order {

		if ( WooCommerce_Integration::class !== $integration_id ) {
			return null;
		}

		$order = wc_get_order( $order_post_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			// TODO: log... trashed?
			return null;
		}

		return $order;
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

		$wc_order = $this->get_wc_order( $integration_id, $order_post_id );

		if ( ! $wc_order ) {
			return;
		}

		// TODO: Should also update the confirmed_received amount on the order meta.

		$this->api_woocommerce->add_order_note_for_transactions(
			$wc_order,
			$check_address_for_payment_service_result->get_new_transactions()
		);
	}
			return;
		}

		$this->api_woocommerce->add_order_note_for_transactions(
			$order,
			$check_address_for_payment_service_result->get_new_transactions()
		);
	}
}
