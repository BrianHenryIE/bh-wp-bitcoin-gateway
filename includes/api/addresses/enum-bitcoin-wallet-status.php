<?php
/**
 * The list of potential statuses a Bitcoin_Wallet could be in.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

enum Bitcoin_Wallet_Status: string {

	case ACTIVE   = 'active';
	case INACTIVE = 'inactive';

	// Inherent WordPress status.
	case DRAFT   = 'draft';
	case PUBLISH = 'publish';
	case ALL     = 'all';
}
