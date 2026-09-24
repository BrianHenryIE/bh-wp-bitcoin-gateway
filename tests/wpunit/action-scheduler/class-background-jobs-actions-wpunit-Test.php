<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use Codeception\Stub\Expected;
use DateInterval;
use DateTimeImmutable;
use lucatume\WPBrowser\TestCase\WPTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler
 */
class Background_Jobs_Actions_WPUnit_Test extends WPTestCase {

	protected function get_sut(
		?API_Background_Jobs_Interface $api = null,
		?Bitcoin_Wallet_Service $wallet_service = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduler = null,
		?LoggerInterface $logger = null,
	): Background_Jobs_Actions_Handler {
		return new Background_Jobs_Actions_Handler(
			api:$api ?? $this->makeEmpty( API_Background_Jobs_Interface::class ),
			wallet_service: $wallet_service ?? $this->make( Bitcoin_Wallet_Service::class ),
			background_jobs_scheduler: $background_jobs_scheduler ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * @covers ::add_action_scheduler_repeating_actions
	 */
	public function test_add_action_scheduler_repeating_actions(): void {

		$background_jobs_scheduler = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_recurring_update_exchange_rate' => Expected::once(),
				'schedule_recurring_ensure_unused_addresses' => Expected::once(),
				'schedule_single_check_assigned_addresses_for_transactions' => Expected::once(),
			)
		);

		/** @var Background_Jobs_Actions_Interface $sut */
		$sut = $this->get_sut(
			background_jobs_scheduler: $background_jobs_scheduler,
		);

		/**
		 * @see Background_Jobs_Actions_Handler::add_action_scheduler_repeating_actions()
		 */
		$sut->add_action_scheduler_repeating_actions();
	}

	/**
	 * @covers ::check_new_addresses_for_transactions
	 */
	public function test_check_new_addresses_for_transactions_action_rate_limit_failure_reschedules(): void {

		$reset_time = new DateTimeImmutable()->add( new DateInterval( 'P1D' ) );

		$api                       = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'check_new_addresses_for_transactions' => fn() => throw new Rate_Limit_Exception( $reset_time ),
			)
		);
		$background_jobs_scheduler = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_check_newly_generated_bitcoin_addresses_for_transactions' => Expected::once( $reset_time ),
			)
		);

		/** @var Background_Jobs_Actions_Interface $sut */
		$sut = $this->get_sut(
			api: $api,
			background_jobs_scheduler: $background_jobs_scheduler,
		);

		/** @see Background_Jobs_Actions_Handler::check_new_addresses_for_transactions() */
		$sut->check_new_addresses_for_transactions();
	}

	/**
	 * Rate-limit rescheduling is handled inside {@see \BrianHenryIE\WP_Bitcoin_Gateway\API\API::check_assigned_addresses_for_payment()};
	 * this verifies the handler's safety-net catch for other implementations: reschedule at the reset
	 * time and return without scheduling the regular ten-minute follow-up.
	 *
	 * @covers ::check_assigned_addresses_for_transactions
	 */
	public function test_check_assigned_addresses_for_transactions_action_rate_limit_failure_reschedules(): void {

		$reset_time = new DateTimeImmutable()->add( new DateInterval( 'P1D' ) );

		$api                       = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'check_assigned_addresses_for_payment' => fn() => throw new Rate_Limit_Exception( $reset_time ),
			)
		);
		$wallet_service_mock       = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::never(),
			)
		);
		$background_jobs_scheduler = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_single_check_assigned_addresses_for_transactions' => Expected::once( $reset_time ),
			)
		);

		/** @var Background_Jobs_Actions_Interface $sut */
		$sut = $this->get_sut(
			api: $api,
			wallet_service: $wallet_service_mock,
			background_jobs_scheduler: $background_jobs_scheduler,
		);

		/** @see Background_Jobs_Actions_Handler::check_assigned_addresses_for_transactions() */
		$sut->check_assigned_addresses_for_transactions();
	}

	/**
	 * Background job failures previously surfaced only in Action Scheduler's own log.
	 *
	 * @covers ::update_exchange_rate
	 * @covers ::ensure_unused_addresses
	 * @covers ::generate_new_addresses
	 * @covers ::check_new_addresses_for_transactions
	 */
	public function test_jobs_log_and_do_not_throw_on_failure(): void {

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'update_exchange_rate'                 => Expected::once(
					function () {
						throw new \RuntimeException( 'exchange rate failure' );
					}
				),
				'ensure_unused_addresses'              => Expected::once(
					function () {
						throw new \RuntimeException( 'ensure failure' );
					}
				),
				'generate_new_addresses'               => Expected::once(
					function () {
						throw new \TypeError( 'generate failure' );
					}
				),
				'check_new_addresses_for_transactions' => Expected::once(
					function () {
						throw new \RuntimeException( 'check failure' );
					}
				),
			)
		);

		$logger = new ColorLogger();
		$sut    = $this->get_sut( api: $api, logger: $logger );

		$sut->update_exchange_rate();
		$sut->ensure_unused_addresses();
		$sut->generate_new_addresses();
		$sut->check_new_addresses_for_transactions();

		$this->assertTrue( $logger->hasErrorThatContains( 'exchange rate failure' ) );
		$this->assertTrue( $logger->hasErrorThatContains( 'ensure failure' ) );
		$this->assertTrue( $logger->hasErrorThatContains( 'generate failure' ) );
		$this->assertTrue( $logger->hasErrorThatContains( 'check failure' ) );
	}

	/**
	 * @covers ::single_ensure_unused_addresses
	 */
	public function test_single_ensure_unused_addresses_logs_when_wallet_is_gone(): void {

		$wallet_service = $this->make(
			Bitcoin_Wallet_Service::class,
			array(
				'get_wallet_by_wp_post_id' => Expected::once(
					function () {
						throw new \InvalidArgumentException( 'No wallet post 999' );
					}
				),
			)
		);

		$logger = new ColorLogger();
		$sut    = $this->get_sut( wallet_service: $wallet_service, logger: $logger );

		$sut->single_ensure_unused_addresses( 999 );

		$this->assertTrue( $logger->hasErrorThatContains( 'No wallet post 999' ) );
	}
}
