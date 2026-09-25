<?php
/**
 * The list of potential statuses a Bitcoin_Wallet could be in.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet;

use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet;

/**
 * @see Bitcoin_Wallet_WP_Post_Interface
 * @see Post_BH_Bitcoin_Wallet
 */
enum Bitcoin_Wallet_Status: string {

	case ACTIVE   = 'active';
	case INACTIVE = 'inactive';

	// Inherent WordPress statuses an admin can put a post into.
	case DRAFT      = 'draft';
	case PUBLISH    = 'publish';
	case TRASH      = 'trash';
	case AUTO_DRAFT = 'auto-draft';
	case PENDING    = 'pending';
	case PRIVATE    = 'private';
	case FUTURE     = 'future';

	/**
	 * Query value for every live status.
	 *
	 * WP_Query's `any` matches every registered status except those flagged `exclude_from_search`, i.e. it
	 * returns active/inactive/draft/publish wallets but not trashed or auto-draft ones. (`all` is not a
	 * registered status, so it would apply no filter and return trashed wallets to the background jobs.)
	 */
	case ALL = 'any';
}
