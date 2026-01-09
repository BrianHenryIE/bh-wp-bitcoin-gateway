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

	public function get_btc_total_price(): Money;

	public function get_btc_exchange_rate(): BigNumber;

	public function get_address(): Bitcoin_Address;

	public function get_gateway(): ?Bitcoin_Gateway;

	public function set_amount_received( Money $updated_confirmed_value ): void;

	public function set_last_checked_time( DateTimeInterface $last_checked_time ): void;

	public function get_last_checked_time(): ?DateTimeInterface;
}
