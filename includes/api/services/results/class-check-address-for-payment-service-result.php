<?php
/**
 * Query data; resolved transactions; value sent to address.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

/**
 * @used-by Payment_Service::check_address_for_payment()
 * @see Payment_Service::update_address_transactions()
 */
readonly class Check_Address_For_Payment_Service_Result extends Update_Address_Transactions_Result {

	/**
	 * Constructor
	 *
	 * @param Update_Address_Transactions_Result $update_address_transactions_result We first must update transactions before performing the calculations.
	 * @param int                                $blockchain_height The current blockchain height.
	 * @param int                                $required_confirmations The required confirmations to consider this "paid".
	 * @param Money                              $confirmed_received The total received with the required confirmations.
	 */
	public function __construct(
		protected Update_Address_Transactions_Result $update_address_transactions_result, // This is accessed by subclass.
		public int $blockchain_height,
		public int $required_confirmations,
		public Money $confirmed_received,
	) {
		parent::__construct(
			queried_address: $update_address_transactions_result->queried_address,
			known_tx_ids_before: $update_address_transactions_result->known_tx_ids_before,
			all_transactions: $update_address_transactions_result->all_transactions,
		);
	}

	/**
	 * Is the confirmed amount greater than the required amount.
	 *
	 * If the `target_amount` is `null` (addresses that have not been assigned to orders), this will return false.
	 */
	public function is_paid(): bool {
		return (bool) $this->queried_address->get_target_amount()?->isLessThanOrEqualTo( $this->confirmed_received );
	}
}
