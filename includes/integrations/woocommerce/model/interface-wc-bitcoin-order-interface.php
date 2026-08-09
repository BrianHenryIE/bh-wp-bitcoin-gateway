<?php
/**
 *
 * @package brianehnryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use DateTimeInterface;

interface WC_Bitcoin_Order_Interface {

	/**
	 * Get the Bitcoin payment address associated with this order.
	 */
	public function get_bitcoin_address(): Bitcoin_Address;

	/**
	 * A ~proxy for the Bitcoin Address object's last checked time.
	 *
	 * Null when never changed
	 */
	public function get_last_checked_time(): ?DateTimeInterface;

	/**
	 * Get the order's gateway.
	 *
	 * Since the gateway id could change, particularly where there are multiple instances, it may happen that the id
	 * in the order does not match an existing gateway, => return null.
	 */
	public function get_gateway(): ?Bitcoin_Gateway;
}
