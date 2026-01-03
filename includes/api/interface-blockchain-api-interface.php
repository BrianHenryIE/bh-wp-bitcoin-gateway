<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;

interface Blockchain_API_Interface {

	/**
	 * @return int The height of the last mined Bitcoin block.
	 *
	 * @throws Rate_Limit_Exception
	 */
	public function get_blockchain_height(): int;

	/**
	 * Query the Blockchain API for the transactions received at this address.
	 *
	 * @param string $btc_address The payment address to check.
	 *
	 * @return array<string, Transaction_Interface> Txid, data.
	 *
	 * @throws Rate_Limit_Exception
	 */
	public function get_transactions_received( string $btc_address ): array;
}
