<?php
/**
 * Functions for background job for checking addresses, generating addresses, etc.
 *
 * After new orders, wait five minutes and check for payments.
 * While the destination address is waiting for payment, continue to schedue new checks every ten minutes (nblock generation time)
 * Every hour, in case the previous check is not running correctly, check are there assigned Bitcoin addresses that we should check for transactions
 * Schedule background job to generate new addresses as needed (fall below threshold defined elsewhere)
 * After generating new addresses, check for existing transactions to ensure they are available to use
 *
 * TODO we need to always be checking the next address that might be assigned to ensure it is still unused.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Functions to schedule Action Scheduler jobs.
 * Functions to handle `do_action` initiated from Action Scheduler.
 */
class Background_Jobs implements Background_Jobs_Scheduling_Interface, Background_Jobs_Actions_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param API_Background_Jobs_Interface $api Main plugin class.
	 * @param Bitcoin_Address_Repository    $bitcoin_address_repository Object to learn if there are addresses to act on.
	 * @param LoggerInterface               $logger PSR logger.
	 */
	public function __construct(
		protected API_Background_Jobs_Interface $api,
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Schedule a background job to generate new addresses.
	 */
	public function schedule_generate_new_addresses(): void {
		as_schedule_single_action(
			timestamp: time(),
			hook: self::GENERATE_NEW_ADDRESSES_HOOK,
			unique: true
		);
		// TODO: check was it already scheduled.
		$this->logger->info( 'New generate new addresses background job scheduled.' );
	}

	/**
	 * Schedule a background job to check newly generated addresses to see do they have existing transactions.
	 * We will use unused addresses for orders and then consider all transactions seen as related to that order.
	 *
	 * @see self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK
	 *
	 * @param ?DateTimeInterface $datetime Optional time, e.g. 429 reset time, or defaults to immediately.
	 */
	public function schedule_check_newly_generated_bitcoin_addresses_for_transactions(
		?DateTimeInterface $datetime = null,
	): void {
		if ( as_has_scheduled_action( hook: self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK )
			&& ! doing_action( hook_name: self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) ) {
			/** @see https://github.com/woocommerce/action-scheduler/issues/903 */

			$this->logger->info(
				message: 'Background_Jobs::schedule_check_new_addresses_for_transactions already scheduled.',
			);

			return;
		}

		$datetime = $datetime ?? new DateTimeImmutable( 'now' );

		as_schedule_single_action(
			timestamp: $datetime->getTimestamp(),
			hook: self::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK,
		);

		$this->logger->info(
			message: 'Background_Jobs::schedule_check_new_addresses_for_transactions scheduled job at {datetime}.',
			context: array(
				'datetime' => $datetime->format( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * When a new order is placed, let's schedule a check.
	 *
	 * We need time for the customer to pay plus time for the block to be verified.
	 * If there's already a job scheduled for existing assigned orders, we'll leave it alone (its scheduled time should be under 10 minutes, or another new order under 15)
	 * Otherwise we'll schedule it for 15 minutes out.
	 *
	 * Generally, 'newly assigned address' = 'new_order'.
	 */
	public function schedule_check_assigned_bitcoin_address_for_transactions(): void {
		if ( as_has_scheduled_action( self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK )
			&& ! doing_action( self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) ) {
			return;
		}
		$this->schedule_check_assigned_addresses_for_transactions(
			new DateTimeImmutable( 'now' )->add( new DateInterval( 'PT15M' ) )
		);
		// $this->logger->debug( "New order created, `shop_order:{$order_id}`, scheduling background job to check for payments" );
	}

	/**
	 * Schedule the next check for transactions for assigned addresses.
	 *
	 * @param ?DateTimeInterface $date_time In ten minutes for a regular check (time to generate a new block), or use the rate limit reset time.
	 */
	protected function schedule_check_assigned_addresses_for_transactions(
		?DateTimeInterface $date_time = null
	): void {
		if ( as_has_scheduled_action( self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK )
			&& ! doing_action( self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) ) {
			return;
		}

		$date_time = $date_time ?? new DateTimeImmutable( 'now' );
		as_schedule_single_action(
			timestamp: $date_time->getTimestamp(),
			hook: self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK,
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
			$this->schedule_check_newly_generated_bitcoin_addresses_for_transactions(
				$exception->get_reset_time()
			);
		}
	}

	/**
	 * This is really just a failsafe in case the actual check gets unscheduled.
	 * This should do nothing/return early when there are no assigned addresses.
	 * New orders should have already scheduled a check with {@see self::schedule_check_assigned_bitcoin_address_for_transactions()}
	 *
	 * @hooked {@see self::CHECK_FOR_ASSIGNED_ADDRESSES_HOOK}
	 * @see self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK
	 */
	public function schedule_check_for_assigned_addresses_repeating_action(): void {
		if ( as_has_scheduled_action( self::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) ) {
			return;
		}

		if ( ! $this->bitcoin_address_repository->has_assigned_bitcoin_addresses() ) {
			return;
		}

		$this->schedule_check_assigned_addresses_for_transactions(
			new DateTimeImmutable( 'now' )
		);
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
			$result = $this->api->check_assigned_addresses_for_transactions();

		} catch ( Rate_Limit_Exception $rate_limit_exception ) {
			$this->schedule_check_assigned_addresses_for_transactions(
				$rate_limit_exception->get_reset_time()
			);
		}

		// If we are still waiting for payments, schedule another check in ten minutes.
		// TODO: Is this better placed in API class?
		if ( $this->bitcoin_address_repository->has_assigned_bitcoin_addresses() ) {
			$this->schedule_check_assigned_addresses_for_transactions(
				new DateTimeImmutable( 'now' )->add( new DateInterval( 'PT10M' ) )
			);
		}
	}

	/**
	 * On every request, ensure we have the hourly check scheduled.
	 *
	 * @hooked action_scheduler_run_recurring_actions_schedule_hook
	 * @see \ActionScheduler_RecurringActionScheduler
	 *
	 * @used-by BH_WP_Bitcoin_Gateway::define_action_scheduler_hooks()
	 * @see self::schedule_check_for_assigned_addresses_repeating_action()
	 *
	 * @see https://crontab.guru/every-1-hour
	 * @see https://github.com/woocommerce/action-scheduler/issues/749
	 */
	public function ensure_schedule_repeating_actions(): void {
		// TODO: what is the precise behaviour of unique here? If it already exists, it should not change the existing one.
		// TODO: add warning log if thus makes a difference, it shows that the other scheduling was not working correctly.
		as_schedule_cron_action(
			timestamp: time(),
			schedule: '0 * * * *',
			hook: self::CHECK_FOR_ASSIGNED_ADDRESSES_HOOK,
			unique: true,
		);
	}
}
