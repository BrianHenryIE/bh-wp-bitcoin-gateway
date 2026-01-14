<?php
/**
 * Model class representing the result of generating a new wallet.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Get_Wallet_For_Xpub_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;

/**
 * @used-by API::get_wallet_for_master_public_key()
 * @used-by API_Interface::get_wallet_for_master_public_key()
 */
class Wallet_Generation_Result extends Get_Wallet_For_Xpub_Service_Result {

	/**
	 * Constructor.
	 *
	 * @param Get_Wallet_For_Xpub_Service_Result $get_wallet_for_xpub_service_result The data and wallet.
	 * @param bool                               $did_schedule_ensure_addresses Was a background job scheduled to ensure addresses.
	 */
	public function __construct(
		Get_Wallet_For_Xpub_Service_Result $get_wallet_for_xpub_service_result,
		public readonly bool $did_schedule_ensure_addresses,
	) {
		$get_wallet_for_xpub_service_result_array = (array) $get_wallet_for_xpub_service_result;
		parent::__construct( ...$get_wallet_for_xpub_service_result_array );
	}
}
