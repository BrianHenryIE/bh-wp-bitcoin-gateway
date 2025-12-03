<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

enum Bitcoin_Address_Status: string {

	/**
	 * Probably brand new and unchecked.
	 */
	case UNKNOWN = 'unknown';

	/**
	 * New and no order id assigned.
	 */
	case UNUSED = 'unused';

	/**
	 * Assigned to an order, payment incomplete.
	 */
	case ASSIGNED = 'assigned';

	/**
	 * Transactions present and no order id, or and order id assigned and payment complete.
	 */
	case USED = 'used';

	// Inherent WordPress status.
	case DRAFT   = 'draft';
	case PUBLISH = 'publish';
	case ALL     = 'all';
}
