<?php
/**
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;

/**
 */
class Update_Address_Transactions_Result {

	/**
	 * Constructor
	 */
	public function __construct(
		public Bitcoin_Address $address,
		public array $known_tx_ids_before,
		public array $new_transactions,
	) {
	}
}
