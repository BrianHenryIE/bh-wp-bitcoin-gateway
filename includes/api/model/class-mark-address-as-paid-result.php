<?php
/**
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;

/**
 * @see API::mark_address_as_paid()
 */
class Mark_Address_As_Paid_Result {

	/**
	 * Constructor.
	 *
	 * @param Bitcoin_Address        $bitcoin_address The Bitcoin address that was marked as paid.
	 * @param Bitcoin_Address_Status $status_before The status of the address before it was marked as paid.
	 */
	public function __construct(
		protected Bitcoin_Address $bitcoin_address,
		protected Bitcoin_Address_Status $status_before,
	) {
	}
}
