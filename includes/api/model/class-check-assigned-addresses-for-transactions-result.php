<?php
/**
 * Result object for address checking operation.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

/**
 * @used-by API::check_addresses_for_transactions()
 */
readonly class Check_Assigned_Addresses_For_Transactions_Result {
	/**
	 * Constructor.
	 *
	 * @param int $count The number of addresses successfully checked for transactions before the operation stopped due to completion, rate limiting, or errors.
	 */
	public function __construct(
		public int $count,
		// TODO: list addresses.
		// TODO: indicate was it rate limited.
	) {
	}
}
