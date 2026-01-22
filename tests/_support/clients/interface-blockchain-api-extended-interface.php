<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

interface Blockchain_API_Extended_Interface extends Blockchain_API_Interface {

	public function get_received_by_address( string $btc_address, bool $confirmed ): Money;
}
