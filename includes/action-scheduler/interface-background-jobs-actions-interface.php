<?php
/**
 * Functions implemented by Background_Jobs class to hand WordPress actions
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

interface Background_Jobs_Actions_Interface {

	/**
	 *
	 */
	const string UPDATE_EXCHANGE_RATE_HOOK = 'bh_wp_bitcoin_gateway_update_exchange_rate';

	/**
	 * Generating new addresses is math-heavy so we do it in a background task.
	 */
	const string GENERATE_NEW_ADDRESSES_HOOK = 'bh_wp_bitcoin_gateway_generate_new_addresses';

	/**
	 * After generating a new address, we need to determine if it is unused.
	 */
	// const string CHECK_GENERATED_ADDRESSES_TRANSACTIONS_HOOK = 'bh_wp_bitcoin_gateway_check_generated_addresses_transactions';

	/**
	 * Every hour we should check the address that will be used next to ensure it is still unused.
	 */
	const string CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK = 'bh_wp_bitcoin_gateway_check_new_addresses_transactions';

	/**
	 * Fetch all addresses pending payment ("assigned") and query remote API for payments. Handle rate limited responses.
	 * Reschedule a check in ten minutes for addresses still unpaid. This is a non-repeating action when there are no addresses with 'assigned' status.
	 */
	const string CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK = 'bh_wp_bitcoin_gateway_check_assigned_addresses_transactions';

	/**
	 * Once/hour check are there any addresses that need to be checked. This is a repeating action. If there is
	 * a CHECK_UNPAID_ADDRESSES_HOOK action scheduled, this action needs to do nothing.
	 *
	 * @see self::ensure_schedule_repeating_actions()
	 */
	const string CHECK_FOR_ASSIGNED_ADDRESSES_HOOK = 'bh_wp_bitcoin_gateway_check_for_new_addresses_needing_scheduling';

	/**
	 * @see self::GENERATE_NEW_ADDRESSES_HOOK
	 */
	public function generate_new_addresses(): void;

	/**
	 * @see self::CHECK_GENERATED_ADDRESSES_TRANSACTIONS_HOOK
	 */
	// public function check_generated_addresses_for_transactions(): void;

	/**
	 * @see self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK
	 */
	public function check_new_addresses_for_transactions(): void;

	/**
	 * @see self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK
	 */
	public function check_assigned_addresses_for_transactions(): void;

	/**
	 * @see self::CHECK_FOR_ASSIGNED_ADDRESSES_HOOK
	 * @hooked action_scheduler_init
	 */
	public function ensure_schedule_repeating_actions(): void;
}
