<?php
/**
 * Functions implemented by API class, which will be used by {@see Background_Jobs_Actions_Handler} class
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Ensure_Unused_Addresses_Result;

interface API_Background_Jobs_Interface {

	/**
	 * Do the maths to generate new addresses for a wallet.
	 *
	 * @return Addresses_Generation_Result[]
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
	 * Check the list of assigned addresses for new transactions and mark them as complete as appropriate, which
	 * will also mark related orders as paid.
	 *
	 * @throws Rate_Limit_Exception When the remote API refuses too many requests.
	 */
	public function check_assigned_addresses_for_payment(): Check_Assigned_Addresses_For_Transactions_Result;

	/**
	 * Make sure each wallet has payment addresses generated and that they have no previous transactions.
	 *
	 * This is used hourly to check the next prepared addresses have also not been sent transactions (used) for any
	 * reason outside WordPress.
	 *
	 * @see Background_Jobs_Actions_Handler::ensure_unused_addresses()
	 * @see Background_Jobs_Scheduler::schedule_recurring_ensure_unused_addresses()
	 *
	 * TODO: change the return type to an object that communicates did we run into rate limiting or other handled exceptions.
	 *
	 * @param int $required_count How many unused addresses to make available. TODO This runs hourly and on checkout load and on place-order, so there should always be addresses available without slowing down customers before placing their order.
	 *
	 * @return array<string, Ensure_Unused_Addresses_Result> array<wallet_xpub: Ensure_Unused_Addresses_Result>
	 */
	public function ensure_unused_addresses( int $required_count = 2 ): array;

	/**
	 * Fetch the wallet's unused addresses from the db, check they still have no transactions, if they do, mark those
	 * as used and generate new addresses until we find one without transactions.
	 *
	 * @param Bitcoin_Wallet $wallet  The wallet to check / find unused addresses for.
	 * @param int            $required_count The number of available addresses we should have.
	 */
	public function ensure_unused_addresses_for_wallet( Bitcoin_Wallet $wallet, int $required_count = 2 ): Ensure_Unused_Addresses_Result;
}
