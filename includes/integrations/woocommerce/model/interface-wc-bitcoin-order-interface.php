<?php
/**
 *
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\BigNumber;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use DateTimeInterface;

/**
 * @mixin \WC_Order
 */
interface WC_Bitcoin_Order_Interface {

	/**
	 * Get the order total in Bitcoin.
	 *
	 * @return Money The order total in BTC.
	 */
	public function get_btc_total_price(): Money;

	/**
	 * Get the Bitcoin to fiat exchange rate for this order.
	 *
	 * @return Money The exchange rate.
	 */
	public function get_btc_exchange_rate(): Money;

	/**
	 * Get the Bitcoin address assigned to this order.
	 *
	 * @return Bitcoin_Address The Bitcoin address.
	 */
	public function get_address(): Bitcoin_Address;

	/**
	 * Get the payment gateway instance for this order.
	 *
	 * @return ?Bitcoin_Gateway The Bitcoin gateway or null.
	 */
	public function get_gateway(): ?Bitcoin_Gateway;

	/**
	 * Set the amount received in Bitcoin for this order.
	 *
	 * @param Money $updated_confirmed_value The confirmed Bitcoin amount received.
	 */
	public function set_amount_received( Money $updated_confirmed_value ): void;

	/**
	 * Set the timestamp when the address was last checked for payments.
	 *
	 * @param DateTimeInterface $last_checked_time The timestamp of last check.
	 */
	public function set_last_checked_time( DateTimeInterface $last_checked_time ): void;

	/**
	 * Get the timestamp when the address was last checked for payments.
	 *
	 * @return ?DateTimeInterface The last checked time or null if never checked.
	 */
	public function get_last_checked_time(): ?DateTimeInterface;
}
