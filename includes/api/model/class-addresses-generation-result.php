<?php
/**
 * Model class representing the result of generating new addresses for all gateways.
 *
 * Replaces: array{}|array{wallet_post_id:int, new_addresses: array{gateway_id:string, xpub:string, generated_addresses:array<Bitcoin_Address>, generated_addresses_count:int, generated_addresses_post_ids:array<int>, address_index:int}}
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;

/**
 * Addresses generation result model.
 *
 * @see API::generate_new_addresses()
 * @see API_Interface::generate_new_addresses()
 *
 * @phpstan-type Generated_Addresses_Array array{gateway_id:string, xpub:string, generated_addresses:array<Bitcoin_Address>, generated_addresses_count:int, generated_addresses_post_ids:array<int>, address_index:int}
 */
class Addresses_Generation_Result {

	/**
	 * Constructor
	 *
	 * @param Bitcoin_Wallet    $wallet The wallet that the new addresses were generated for.
	 * @param Bitcoin_Address[] $new_addresses The newly generated addresses.
	 * @param ?int              $address_index The new highest wallet address index.
	 */
	public function __construct(
		public Bitcoin_Wallet $wallet,
		public array $new_addresses,
		public int $address_index,
	) {
	}
}
