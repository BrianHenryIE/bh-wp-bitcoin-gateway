<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Rate_Limit_Exception;
use Codeception\Stub\Expected;
use DateInterval;
use DateTimeImmutable;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler
 */
class Background_Jobs_Actions_WPUnit_Test extends WPTestCase {

	/**
	 * @covers ::ensure_schedule_repeating_actions
	 */
	public function test_ensure_schedule_repeating_actions(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$background_jobs_scheduler  = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_ensure_unused_addresses' => Expected::once(),
				'schedule_single_check_assigned_addresses_for_transactions' => Expected::once(),
			)
		);

		/** @var Background_Jobs_Actions_Interface $sut */
		$sut = new Background_Jobs_Actions_Handler( $api, $bitcoin_address_repository, $background_jobs_scheduler, $logger );

		/**
		 * @see Background_Jobs_Actions_Handler::ensure_schedule_repeating_actions()
		 */
		$sut->ensure_schedule_repeating_actions();
	}

	/**
	 * @covers ::check_new_addresses_for_transactions
	 */
	public function test_check_new_addresses_for_transactions_action_rate_limit_failure_reschedules(): void {

		$logger = new ColorLogger();

		$reset_time = new DateTimeImmutable()->add( new DateInterval( 'P1D' ) );

		$api                        = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'check_new_addresses_for_transactions' => fn() => throw new Rate_Limit_Exception( $reset_time ),
			)
		);
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$background_jobs_scheduler  = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_check_newly_generated_bitcoin_addresses_for_transactions' => Expected::once( $reset_time ),
			)
		);

		/** @var Background_Jobs_Actions_Interface $sut */
		$sut = new Background_Jobs_Actions_Handler( $api, $bitcoin_address_repository, $background_jobs_scheduler, $logger );

		/** @see Background_Jobs_Actions_Handler::check_new_addresses_for_transactions() */
		$sut->check_new_addresses_for_transactions();
	}

	/**
	 * @covers ::check_assigned_addresses_for_transactions
	 */
	public function test_check_assigned_addresses_for_transactions_action_rate_limit_failure_reschedules(): void {

		$logger = new ColorLogger();

		$reset_time = new DateTimeImmutable()->add( new DateInterval( 'P1D' ) );

		$api                        = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'check_assigned_addresses_for_payment' => fn() => throw new Rate_Limit_Exception( $reset_time ),
			)
		);
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		$background_jobs_scheduler = $this->makeEmpty(
			Background_Jobs_Scheduler_Interface::class,
			array(
				'schedule_check_assigned_addresses_for_transactions' => Expected::once(),
			// 'schedule_check_assigned_addresses_for_transactions' => Expected::once( $reset_time ),
			)
		);

		/** @var Background_Jobs_Actions_Interface $sut */
		$sut = new Background_Jobs_Actions_Handler( $api, $bitcoin_address_repository, $background_jobs_scheduler, $logger );

		/** @see Background_Jobs_Actions_Handler::check_assigned_addresses_for_transactions() */
		$sut->check_assigned_addresses_for_transactions();

		// assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
		// $this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}
}
