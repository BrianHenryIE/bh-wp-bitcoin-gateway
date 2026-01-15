<?php
/**
 * Ensure:
 * * the exchange rate is up-to-date
 * * there are unused addresses available for orders
 * * assigned addresses are checked for payments
 *
 * After new orders, wait five minutes and check for payments.
 * While the destination address is waiting for payment, continue to schedule new checks every ten minutes (nblock generation time)
 * Every hour, in case the previous check is not running correctly, check are there assigned Bitcoin addresses that we should check for transactions
 * Schedule background job to generate new addresses as needed (fall below threshold defined elsewhere)
 * After generating new addresses, check for existing transactions to ensure they are available to use
 *
 * TODO we need to always be checking the next address that might be assigned to ensure it is still unused.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\BH_WP_Bitcoin_Gateway;
use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Functions to handle `do_action` initiated from Action Scheduler.
 */
class Background_Jobs_Actions_Handler implements Background_Jobs_Actions_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param API_Background_Jobs_Interface       $api Main plugin class.
	 * @param Bitcoin_Wallet_Service              $wallet_service Used to convert post_id into Bitcoin_Wallet and check are there addresses to check.
	 * @param Background_Jobs_Scheduler_Interface $background_jobs_scheduler Uses Action Scheduler `as_*` functions to invoke this class's functions during cron/background.
	 * @param LoggerInterface                     $logger PSR logger.
	 */
	public function __construct(
		protected API_Background_Jobs_Interface $api,
		protected Bitcoin_Wallet_Service $wallet_service,
		protected Background_Jobs_Scheduler_Interface $background_jobs_scheduler,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Using Action Scheduler's "schedule" hook, set up our own repeating jobs.
	 *
	 * @hooked action_scheduler_run_recurring_actions_schedule_hook
	 * @see \ActionScheduler_RecurringActionScheduler
	 *
	 * @used-by BH_WP_Bitcoin_Gateway::define_action_scheduler_hooks()
	 * @see Background_Jobs_Scheduler_Interface::schedule_check_for_assigned_addresses_repeating_action()
	 *
	 * @see https://crontab.guru/every-1-hour
	 * @see https://github.com/woocommerce/action-scheduler/issues/749
	 */
	public function add_action_scheduler_repeating_actions(): void {
		$this->background_jobs_scheduler->schedule_recurring_ensure_unused_addresses();
		$this->background_jobs_scheduler->schedule_single_check_assigned_addresses_for_transactions();
	}

	/**
	 * TODO: add the add_action code!
	 *
	 * @hooked Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK
	 */
	public function ensure_unused_addresses(): void {

		$this->logger->debug( 'Starting ensure_unused_addresses() background job.' );

		// TODO: return a meaningful result and log it.
		$result = $this->api->ensure_unused_addresses();
	}

	/**
	 * Check a wallet (blockchain API calls) to make sure we have some unused payment addresses available for it.
	 *
	 * Used when a wallet is created and when a payment address is assigned to an order.
	 *
	 * @see Background_Jobs_Actions_Interface::single_ensure_unused_addresses()
	 * @see BH_WP_Bitcoin_Gateway::define_action_scheduler_hooks()
	 *
	 * @param int $wallet_post_id Deserialised argument passed from Action Scheduler.
	 */
	public function single_ensure_unused_addresses( int $wallet_post_id ): void {
		$this->logger->debug( 'Starting `single_ensure_unused_addresses()` background job for `wallet_post_id:' . $wallet_post_id . '`.' );

		$wallet = $this->wallet_service->get_wallet_by_wp_post_id( $wallet_post_id );

		$result = $this->api->ensure_unused_addresses_for_wallet( $wallet );

		$this->logger->info(
			'Finished `single_ensure_unused_addresses()` background job for `wallet_post_id:' . $wallet_post_id . '`.',
			(array) $result
		);
	}

	/**
	 * When available addresses fall below a threshold, more are generated on a background job.
	 *
	 * @hooked bh_wp_bitcoin_gateway_generate_new_addresses
	 * @see self::GENERATE_NEW_ADDRESSES_HOOK
	 */
	public function generate_new_addresses(): void {

		$this->logger->debug( 'Starting generate_new_addresses() background job.' );

		// TODO: return a meaningful result and log it.
		$result = $this->api->generate_new_addresses();
	}

	/**
	 * After new addresses have been created, we check to see are they fresh/available to use.
	 * TODO It's not unlikely we'll hit 429 rate limits during this, so we'll loop through as many as we can,
	 * then schedule a new job when we're told to stop.
	 *
	 * @hooked {@see self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK}
	 */
	public function check_new_addresses_for_transactions(): void {

		$this->logger->debug( 'Starting check_new_addresses_for_transactions() background job.' );

		try {
			$result = $this->api->check_new_addresses_for_transactions();
		} catch ( Rate_Limit_Exception $exception ) {
			$this->background_jobs_scheduler->schedule_check_newly_generated_bitcoin_addresses_for_transactions(
				$exception->get_reset_time()
			);
		}
	}

	/**
	 * Fetch all the addresses pending payments, ordered by last updated
	 * query the Blockchain API for updates,
	 * on rate-limit error, reschedule a check after the rate limit expires,
	 * reschedule another check in ten minutes if there are still addresses awaiting payment.
	 *
	 * TODO: ensure addresses' updated date is changed after querying for transactions
	 * TODO: use wp_comments table to log
	 *
	 * If we have failed to check all the addresses that we should, so let's reschedule the check when
	 * the rate limit expires. The addresses that were successfully checked should have their updated
	 * time updated, so the next addresses in sequence will be the next checked.
	 * TODO: should the rescheduling be handled here or in the API class?
	 *
	 * @hooked {@see self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK}
	 */
	public function check_assigned_addresses_for_transactions(): void {

		$this->logger->info( 'Starting check_assigned_addresses_for_transactions() background job.' );

		try {
			$result = $this->api->check_assigned_addresses_for_payment();

		} catch ( Rate_Limit_Exception $rate_limit_exception ) {
			$this->background_jobs_scheduler->schedule_single_check_assigned_addresses_for_transactions(
				$rate_limit_exception->get_reset_time()
			);
		}

		// If we are still waiting for payments, schedule another check in ten minutes.
		// TODO: Is this better placed in API class?
		if ( $this->wallet_service->has_assigned_bitcoin_addresses() ) {
			$this->background_jobs_scheduler->schedule_single_check_assigned_addresses_for_transactions(
				new DateTimeImmutable( 'now' )->add( new DateInterval( 'PT10M' ) )
			);
		}
	}
}
