<?php
/**
 * Functions for background job for checking addresses, generating addresses, etc.
 *
 * Every hour Action Scheduler's `action_scheduler_run_recurring_actions_schedule_hook` runs. We hook into this to
 * register our own recurring hourly job, and schedule a single job to check assigned addresses.
 *
 * The hourly job ensures there is always two addresses that are unused. I.e. previously generated
 * addresses may have been used outside WordPress and it is difficult to use transaction data to confirm payment when
 * transactions may not be for the purpose of the order.
 *
 * The single job checks for payments and TODO: the action reschedules itself if there are still unpaid addresses to check.
 * This job may already be scheduled when an address is assigned to an order, or from its own self-rescheduling.
 *
 * TODO: If we see a wallet being used outside WordPress should we suggest setting up an exclusive wallet?
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Functions to schedule Action Scheduler jobs.
 */
class Background_Jobs_Scheduler implements Background_Jobs_Scheduler_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param Bitcoin_Address_Repository $bitcoin_address_repository Object to learn if there are addresses to act on.
	 * @param LoggerInterface            $logger PSR logger.
	 */
	public function __construct(
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * This is hooked to Action Scheduler's repeating action and schedules the ensure_unused_addresses job for
	 * once/hour. To schedule it to run immediately, use the below method schedule_single...
	 *
	 * @see self::schedule_single_ensure_unused_addresses()
	 * @used-by Background_Jobs_Actions_Interface::add_action_scheduler_repeating_actions()
	 */
	public function schedule_recurring_ensure_unused_addresses(): void {
		if ( as_has_scheduled_action( hook: Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK ) ) {
			$this->logger->debug(
				message: 'Background_Jobs schedule_ensure_unused_addresses already scheduled.',
			);
			return;
		}

		$result = as_schedule_recurring_action(
			timestamp: time(),
			interval_in_seconds: constant( 'HOUR_IN_SECONDS' ),
			hook: Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK,
			unique: true,
		);

		if ( 0 === $result ) {
			$this->logger->error( 'Background_Jobs schedule_ensure_unused_addresses failed.' );
			return;
		}

		$this->logger->info( 'Background_Jobs schedule_ensure_unused_addresses hourly job added.' );
	}

	/**
	 * Schedule and immediate background job to check/generate unused addresses for the wallet.
	 *
	 * When a new wallet is made, this is immediately called to schedule a job to ensure there is one payable address
	 * ready. TODO: When an address is assigned, this is called to queue up the next one.
	 *
	 * Functions using `API::generate_new_addresses_for_wallet()` elsewhere can decide to synchronously call the API
	 * to check are addresses unused, if that's the case, this would exit quickly anyway.
	 *
	 * @see Background_Jobs_Actions_Handler::single_ensure_unused_addresses()
	 *
	 * @used-by API::generate_new_addresses_for_wallet()
	 *
	 * @param Bitcoin_Wallet $wallet The wallet that may need payment addresses generated.
	 */
	public function schedule_single_ensure_unused_addresses( Bitcoin_Wallet $wallet ): void {
		if ( as_has_scheduled_action(
			hook: Background_Jobs_Actions_Interface::SINGLE_ENSURE_UNUSED_ADDRESSES_HOOK,
			args: array(
				'wallet_post_id' => $wallet->get_post_id(),
			)
		)
		) {
			// This will probably never happen, but in case this were called from a loop by mistake.
			$this->logger->debug(
				'Background_Jobs schedule_single_ensure_unused_addresses unexpectedly already scheduled for {wallet_id} {wallet_xpub}.',
				array(
					'wallet_id'   => $wallet->get_post_id(),
					'wallet_xpub' => $wallet->get_xpub(),
				)
			);
			return;
		}

		$date_time = new DateTimeImmutable( 'now' );
		$action_id = as_schedule_single_action(
			timestamp: $date_time->getTimestamp(),
			hook: Background_Jobs_Actions_Interface::SINGLE_ENSURE_UNUSED_ADDRESSES_HOOK,
			args: array(
				'wallet_post_id' => $wallet->get_post_id(),
			)
		);
	}

	/**
	 * Schedule the next check for transactions for assigned addresses.
	 *
	 * @hooked
	 *
	 * @used-by Background_Jobs_Actions_Interface::add_action_scheduler_repeating_actions()
	 * TODO: When a new order is placed, let's schedule a check (in ten minutes).
	 *
	 * @param ?DateTimeInterface $date_time In ten minutes for a regular check (time to generate a new block), or use the rate limit reset time.
	 * e.g. `new DateTimeImmutable( 'now' )->add( new DateInterval( 'PT15M' ) )`.
	 */
	public function schedule_single_check_assigned_addresses_for_transactions(
		?DateTimeInterface $date_time = null
	): void {
		if ( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK )
			&& ! doing_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) ) {
			return;
		}

		if ( ! $this->bitcoin_address_repository->has_assigned_bitcoin_addresses() ) {
			return;
		}

		$date_time = $date_time ?? new DateTimeImmutable( 'now' );
		as_schedule_single_action(
			timestamp: $date_time->getTimestamp(),
			hook: Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK,
		);
	}

	/**
	 * Schedule a background job to generate new addresses.
	 */
	public function schedule_generate_new_addresses(): void {
		as_schedule_single_action(
			timestamp: time(),
			hook: Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK,
			unique: true
		);
		// TODO: check was it already scheduled.
		$this->logger->info( 'New generate new addresses background job scheduled.' );
	}

	/**
	 * Schedule a background job to check newly generated addresses to see do they have existing transactions.
	 * We will use unused addresses for orders and then consider all transactions seen as related to that order.
	 *
	 * This is a background job so when we hit a rate limit we can re-run later to complete.
	 *
	 * @see self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK
	 *
	 * @param ?DateTimeInterface $datetime Optional time, e.g. 429 reset time, or defaults to immediately.
	 */
	public function schedule_check_newly_generated_bitcoin_addresses_for_transactions(
		?DateTimeInterface $datetime = null,
	): void {
		if ( as_has_scheduled_action( hook: Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK )
			&& ! doing_action( hook_name: Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) ) {
			/** @see https://github.com/woocommerce/action-scheduler/issues/903 */

			$this->logger->info(
				message: 'Background_Jobs::schedule_check_new_addresses_for_transactions already scheduled.',
			);

			return;
		}

		$datetime = $datetime ?? new DateTimeImmutable( 'now' );

		as_schedule_single_action(
			timestamp: $datetime->getTimestamp(),
			hook: Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK,
		);

		$this->logger->info(
			message: 'Background_Jobs::schedule_check_new_addresses_for_transactions scheduled job at {datetime}.',
			context: array(
				'datetime' => $datetime->format( 'Y-m-d H:i:s' ),
			)
		);
	}
}
