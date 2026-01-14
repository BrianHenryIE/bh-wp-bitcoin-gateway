<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Rate_Limit_Exception;
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
		$wallet_service_mock = $this->makeEmpty( Bitcoin_Wallet_Service::class,
		array(
			'has_assigned_bitcoin_addresses' => Expected::once(false),
		));
		$background_jobs_scheduler = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_check_assigned_addresses_for_transactions' => Expected::once(),
			// 'schedule_check_assigned_addresses_for_transactions' => Expected::once( $reset_time ),
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

		$this->markTestIncomplete( 'I think the rate-limit rescheduling should be done in the API class.' );

		// assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
		// $this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}
}
