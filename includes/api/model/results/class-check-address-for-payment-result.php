<?php
/**
 * Details of query / transactions / calculations and the updated address object after checking for payment.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Check_Address_For_Payment_Service_Result;

/**
 * @used-by API::check_address_for_payment()
 */
class Check_Address_For_Payment_Result extends Check_Address_For_Payment_Service_Result {

	/**
	 * Constructor
	 *
	 * @param Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result The result from the service that performed the API calls and calculations.
	 * @param bool                                     $is_paid When the confirmed amount received is greater than/equal to the amount required, as set on the address object.
	 * @param Bitcoin_Address                          $refreshed_address The address object in its latest state.
	 */
	public function __construct(
		Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result,
		public readonly bool $is_paid,
		public readonly Bitcoin_Address $refreshed_address,
	) {
		parent::__construct(
			update_address_transactions_result: $check_address_for_payment_service_result->update_address_transactions_result,
			blockchain_height: $check_address_for_payment_service_result->blockchain_height,
			required_confirmations: $check_address_for_payment_service_result->required_confirmations,
			confirmed_received: $check_address_for_payment_service_result->confirmed_received,
		);
	}
}
