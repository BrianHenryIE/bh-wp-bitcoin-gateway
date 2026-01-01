<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Address_Balance;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeInterface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;

interface Blockchain_API_Interface {

	public function get_blockchain_height(): int;

	/**
	 * The total amount in BTC received at this address.
	 *
	 * @param string $btc_address The payment address to check.
	 * @param bool   $confirmed
	 */
	// public function get_received_by_address( string $btc_address, bool $confirmed ): Money;

	/**
	 * The current balance of the address.
	 *
	 * @param string $btc_address The payment address to check.
	 * @param int    $number_of_confirmations
	 */
	// public function get_address_balance( string $btc_address, int $number_of_confirmations ): Address_Balance;

	/**
	 * Query the Blockchain API for the transactions received at this address.
	 *
	 * @param string $btc_address The payment address to check.
	 *
	 * @return array<string, Transaction_Interface> Txid, data.
	 */
	public function get_transactions_received( string $btc_address ): array;
}
