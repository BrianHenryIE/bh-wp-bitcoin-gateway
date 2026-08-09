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

	// Inherent WordPress status.
	case DRAFT   = 'draft';
	case PUBLISH = 'publish';
	case ALL     = 'all';
}
