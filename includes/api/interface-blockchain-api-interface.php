<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;

interface Blockchain_API_Interface {

	/**
	 * @return int The height of the last mined Bitcoin block.
	 *
	 * @throws Rate_Limit_Exception When the blockchain API rate limit has been exceeded and the request is throttled.
	 */
	public function get_blockchain_height(): int;

	/**
	 * Query the Blockchain API for the transactions received at this address.
	 *
	 * @param string $btc_address The payment address to check.
	 *
	 * @return array<string, Transaction> Txid, data.
	 *
	 * @throws Rate_Limit_Exception When the blockchain API rate limit has been exceeded while querying for address transactions.
	 */
	public function get_transactions_received( string $btc_address ): array;
}
