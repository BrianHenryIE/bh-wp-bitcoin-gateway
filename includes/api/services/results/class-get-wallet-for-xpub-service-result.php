<?php
/**
 * The master public key string (and optional gateway_id) used to find/create the wallet object, and the resulting
 * wallet and whether it was newly created or not.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;

/**
 * @used-by Bitcoin_Wallet_Service::get_wallet_for_xpub()
 */
class Get_Wallet_For_Xpub_Service_Result {

	/**
	 * Constructor
	 *
	 * @param string         $xpub The master public key we need a wallet for.
	 * @param ?string        $gateway_id Optional gateway id to associate the wallet with.
	 * @param Bitcoin_Wallet $wallet The created/found wallet.
	 * @param bool           $is_new Was the wallet new or existing.
	 */
	public function __construct(
		public readonly string $xpub,
		public readonly ?string $gateway_id,
		public readonly Bitcoin_Wallet $wallet,
		public readonly bool $is_new,
	) {
	}
}
