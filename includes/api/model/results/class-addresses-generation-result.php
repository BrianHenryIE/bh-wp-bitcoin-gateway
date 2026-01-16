<?php
/**
 * Model class representing the result of generating new addresses for a wallet.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;

/**
 * Addresses generation result model.
 *
 * @see API::generate_new_addresses()
 * @see API_Interface::generate_new_addresses()
 *
 * @used-by Bitcoin_Wallet_Service::generate_new_addresses_for_wallet()
 */
readonly class Addresses_Generation_Result {

	/**
	 * Constructor
	 *
	 * @param Bitcoin_Wallet                   $wallet The wallet that the new addresses were generated for, refreshed.
	 * @param non-empty-array<Bitcoin_Address> $new_addresses The newly generated addresses.
	 * @param array<Bitcoin_Address>           $orphaned_addresses Existing saved addresses with incorrect wallet post id.
	 * @param ?int                             $prior_address_index The new highest wallet address index.
	 */
	public function __construct(
		public Bitcoin_Wallet $wallet,
		public array $new_addresses,
		public array $orphaned_addresses = array(),
		public ?int $prior_address_index = null,
	) {
	}

	/**
	 * Get the index of the highest (known) derived address.
	 */
	public function get_highest_address_index(): ?int {
		return $this->wallet->get_address_index();
	}
}
