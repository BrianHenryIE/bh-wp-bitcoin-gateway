<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers;

interface Generate_Address_API_Interface {

	/**
	 * Generates a new address from a master public address and an index.
	 *
	 * @param string $public_address xpub/ypub/zpub address.
	 * @param int    $nth    The index of the address to generate.
	 *
	 * @return string Raw address.
	 */
	public function generate_address( string $public_address, int $nth ): string;
}
