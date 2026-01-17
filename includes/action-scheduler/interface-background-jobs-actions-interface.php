<?php
/**
 * Functions implemented by Background_Jobs class to hand WordPress actions
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\WP_Bitcoin_Gateway\BH_WP_Bitcoin_Gateway;

/**
 * `do_action( HOOK_NAME )` is called by Action Scheduler / wp_cron to run these tasks.
 * We `add_action( HOOK_NAME )` to our implementations of the functions below to handle them.
 *
 * @see BH_WP_Bitcoin_Gateway::define_action_scheduler_hooks()
 */
interface Background_Jobs_Actions_Interface {

	/**
	 * The hook name for updating exchange rates.
	 */
	const string UPDATE_EXCHANGE_RATE_HOOK = 'bh_wp_bitcoin_gateway_update_exchange_rate';

	/**
	 * The hook name for regularly ensuring there is an unused payment address available.
	 */
	const string RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK = 'bh_wp_bitcoin_gateway_recurring_ensure_unused_addresses';

	/**
	 * The hook name for a single check to ensure there is an unused address available, use e.g. immediately after
	 * one is assigned to an order.
	 */
	const string SINGLE_ENSURE_UNUSED_ADDRESSES_HOOK = 'bh_wp_bitcoin_gateway_single_ensure_unused_addresses';

	/**
	 * Fetch all addresses pending payment ("assigned") and query remote API for payments. Handle rate limited responses.
	 * Reschedule a check in ten minutes for addresses still unpaid. This is a non-repeating action when there are no addresses with 'assigned' status.
	 */
	const string CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK = 'bh_wp_bitcoin_gateway_check_assigned_addresses_transactions';

	/**
	 * Generating new addresses is math-heavy so we do it in a background task.
	 */
	const string GENERATE_NEW_ADDRESSES_HOOK = 'bh_wp_bitcoin_gateway_generate_new_addresses';

	/**
	 * Every hour we should check the address that will be used next to ensure it is still unused.
	 */
	const string CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK = 'bh_wp_bitcoin_gateway_check_new_addresses_transactions';

	/**
	 * @hooked action_scheduler_run_recurring_actions_schedule_hook
	 */
	public function add_action_scheduler_repeating_actions(): void;

	/**
	 * Handler for recurring job to update the exchange rate.
	 *
	 * @hooked bh_wp_bitcoin_gateway_update_exchange_rate
	 * @see self::UPDATE_EXCHANGE_RATE_HOOK
	 */
	public function update_exchange_rate(): void;

	/**
	 * Handler for recurring job to check we have addresses ready for new orders.
	 */
	public function ensure_unused_addresses(): void;

	/**
	 * Handler for one-off checks for available addresses (called after an address has been used).
	 *
	 * @param int $wallet_post_id Id of the wallet to check.
	 */
	public function single_ensure_unused_addresses( int $wallet_post_id ): void;

	/**
	 * @see self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK
	 */
	public function check_assigned_addresses_for_transactions(): void;

	/**
	 * @see self::GENERATE_NEW_ADDRESSES_HOOK
	 */
	public function generate_new_addresses(): void;

	/**
	 * @see self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK
	 */
	public function check_new_addresses_for_transactions(): void;
}
