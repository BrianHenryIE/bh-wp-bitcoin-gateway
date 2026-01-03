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
	 * @param Bitcoin_Address[] $existing_unused_addresses The unused addresses that were already known.
	 * @param Bitcoin_Address[] $new_unused_addresses The newly generated unused addresses.
	 */
	public function __construct(
		public Bitcoin_Wallet $wallet,
		public array $existing_unused_addresses,
		public array $new_unused_addresses,
	) {
	}
}
