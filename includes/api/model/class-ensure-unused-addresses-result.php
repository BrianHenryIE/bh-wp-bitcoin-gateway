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
class Ensure_Unused_Addresses_Result {

	/**
	 * Constructor
	 *
	 * @param Bitcoin_Wallet    $wallet The wallet that the new addresses were generated for.
	 * @param Bitcoin_Address[] $assumed_existing_unused_addresses Wallet's addresses that were saved as unused.
	 * @param Bitcoin_Address[] $actual_existing_unused_addresses The assumed addresses that were correctly marked as unused.
	 * @param Bitcoin_Address[] $unexpectedly_used_addresses_by_wallet Addresses that had been marked unused but which did have transactions.
	 * @param Bitcoin_Address[] $new_unused_addresses The newly generated unused addresses.
	 */
	public function __construct(
		public Bitcoin_Wallet $wallet,
		public array $assumed_existing_unused_addresses,
		public array $actual_existing_unused_addresses,
		public array $unexpectedly_used_addresses_by_wallet,
		public array $new_unused_addresses,
	) {
	}
}
