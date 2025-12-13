<?php
/**
 * Functions implemented by API class, which will be used by {@see Background_Jobs} class
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;

interface API_Background_Jobs_Interface {

	/**
	 * Do the maths to generate new addresses for a wallet.
	 */
	public function generate_new_addresses(): array;

	/**
	 * Make sure newly generated addresses have no existing transactions, so we only use unused addresses for orders.
	 *
	 * This is different from {@see self::check_assigned_addresses_for_payment} in that the post status will go from
	 * new to used rather than from assigned to completed.
	 *
	 * @throws Rate_Limit_Exception When the remote API refuses too many requests.
	 */
	public function check_new_addresses_for_transactions(): Check_Assigned_Addresses_For_Transactions_Result;

	/**
	 * Check the list of assigned addressess for new transactions and mark them as complete as appropriate, which
	 * will also mark related orders as paid.
	 *
	 * @throws Rate_Limit_Exception When the remote API refuses too many requests.
	 */
	public function check_assigned_addresses_for_payment(): Check_Assigned_Addresses_For_Transactions_Result;
}
