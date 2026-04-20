<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;

// I think we need every client to implement the ~`bh_wp_bitcoin_blockchain_api_link` descriptive filter to be able to
// attribute the information we have with where it came from.
//
// . E.g. so we can get the
// url to link to the details of a transaction. SO we can att

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
