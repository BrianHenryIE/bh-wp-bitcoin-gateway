<?php
/**
 * API interface for WooCommerce integration.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\BigNumber;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order_Interface;
use WC_Order;

interface API_WooCommerce_Interface extends API_Interface {

	/**
	 * Given an order id, determine is the order's gateway an instance of this Bitcoin gateway.
	 *
	 * @see https://github.com/BrianHenryIE/bh-wc-duplicate-payment-gateways
	 *
	 * @param int|numeric-string $order_id A WooCommerce order id (presumably).
	 */
	public function is_order_has_bitcoin_gateway( int|string $order_id ): bool;

	/**
	 * Given a gateway id as a string, determine is it an instance of this Bitcoin gateway.
	 *
	 * @see https://github.com/BrianHenryIE/bh-wc-duplicate-payment-gateways
	 *
	 * @param string $gateway_id The WC_Payment_Gateway id to be checked.
	 */
	public function is_bitcoin_gateway( string $gateway_id ): bool;

	/**
	 * Get a list of payment gateways registered with WooCommerce which are instances of Bitcoin_Gateway.
	 *
	 * @return Bitcoin_Gateway[]
	 */
	public function get_bitcoin_gateways(): array;

	/**
	 * Gets an unused payment address, adds metadata on both the order and address linking them.
	 *
	 * @param WC_Order $order The (newly placed) WooCommerce order.
	 * @param Money    $btc_total The total order amount in Bitcoin.
	 *
	 * @return Bitcoin_Address
	 * @throws BH_WP_Bitcoin_Gateway_Exception When no address is available.
	 */
	public function get_fresh_address_for_order( WC_Order $order, Money $btc_total ): Bitcoin_Address;

	/**
	 * Return the current Bitcoin details for an order, optionally refresh.
	 *
	 * @param WC_Order $wc_order   WooCommerce order object.
	 * @param bool     $refresh Query remote APIs to refresh the details, or just return cached data.
	 *
	 * @return WC_Bitcoin_Order_Interface
	 */
	public function get_order_details( WC_Order $wc_order, bool $refresh = true ): WC_Bitcoin_Order_Interface;

	/**
	 * Determine do we have any fresh address available for this gateway.
	 * Used so the gateway is not displayed at checkout if there are no addresses ready.
	 *
	 * @used-by Bitcoin_Gateway::is_available()
	 *
	 * @param Bitcoin_Gateway $gateway The WooCommerce payment gateway which should have addresses generated.
	 */
	public function is_fresh_address_available_for_gateway( Bitcoin_Gateway $gateway ): bool;

	/**
	 * Get a single unused Bitcoin address for a given payment gateway's wallet.
	 *
	 * @param Bitcoin_Gateway $gateway The Bitcoin payment gateway.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception
	 */
	public function get_fresh_address_for_gateway( Bitcoin_Gateway $gateway ): ?Bitcoin_Address;

	/**
	 * Returns the array from `get_order_details()` with additional keys for printing in HTML/email.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param bool     $refresh Should an API request be made to check for new transactions, or just use existing data.
	 *
	 * @return array<string, string|null|Money|BigNumber|array<Bitcoin_Transaction>>
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the order has no Bitcoin address.
	 */
	public function get_formatted_order_details( WC_Order $order, bool $refresh = true ): array;
}
