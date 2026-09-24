<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet;

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

	// Inherent WordPress statuses an admin can put a post into.
	case TRASH      = 'trash';
	case DRAFT      = 'draft';
	case PUBLISH    = 'publish';
	case AUTO_DRAFT = 'auto-draft';
	case PENDING    = 'pending';
	case PRIVATE    = 'private';
	case FUTURE     = 'future';

	/**
	 * Query value for every live status.
	 *
	 * @see Bitcoin_Wallet_Status::ALL
	 */
	case ALL = 'any';
}
