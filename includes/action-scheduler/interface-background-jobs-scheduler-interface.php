<?php
/**
 * Functions implemented by Background_Jobs class, used by API class to schedule jobs.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

// TODO: every hour the exchange rate needs to be checked. When there are no new orders, we still need to check the
// spare addresses so we know they haven't been used for some purpose outside here that might result in an order being
// marked paid by mistake. Obv. when we have unpaid orders, we check them. Until?

// ActionScheduler has an action/hook to listen to whose purpose is making sure no cron job has been forgotten.

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use DateTimeInterface;

interface Background_Jobs_Scheduler_Interface {
	/**
	 * Schedule a recurring job to ensure there are unused addresses available for orders.
	 */
	public function schedule_recurring_ensure_unused_addresses(): void;

	/**
	 * Schedule a single job to ensure unused addresses for a specific wallet.
	 *
	 * @param Bitcoin_Wallet $wallet The wallet to check.
	 */
	public function schedule_single_ensure_unused_addresses( Bitcoin_Wallet $wallet ): void;

	/**
	 * Schedule a single job to check assigned addresses for transactions.
	 *
	 * @param ?DateTimeInterface $date_time Optional datetime to schedule the job.
	 */
	public function schedule_single_check_assigned_addresses_for_transactions( ?DateTimeInterface $date_time = null ): void;

	/**
	 * Schedule a job to generate new addresses.
	 *
	 * @deprecated In favour of ensure-unused-addresses functions.
	 */
	public function schedule_generate_new_addresses(): void;

	/**
	 * Schedule a job to check newly generated Bitcoin addresses for transactions.
	 *
	 * @param ?DateTimeInterface $datetime Optional datetime to schedule the job.
	 */
	public function schedule_check_newly_generated_bitcoin_addresses_for_transactions( ?DateTimeInterface $datetime = null ): void;

	// schedule exchange rate update.
}
