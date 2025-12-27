<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use ActionScheduler_Abstract_Schedule;
use ActionScheduler_Action;
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
class Background_Jobs_Scheduler_WPUnit_Test extends WPTestCase {

	/**
	 * @covers ::schedule_generate_new_addresses
	 */
	public function test_schedule_generate_new_addresses(): void {

		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK ) );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		/**
		 * @see Background_Jobs_Actions_Handler::schedule_generate_new_addresses()
		 */
		$sut->schedule_generate_new_addresses();

		$this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK ) );
	}

	/**
	 * @covers ::schedule_check_newly_generated_bitcoin_addresses_for_transactions
	 */
	public function test_schedule_check_newly_generated_bitcoin_addresses_for_transactions(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$background_jobs_scheduler  = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		/**
		 * @see Background_Jobs_Actions_Handler::schedule_check_newly_generated_bitcoin_addresses_for_transactions()
		 */
		$sut->schedule_check_newly_generated_bitcoin_addresses_for_transactions();

		$this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );
	}

	/**
	 * @covers ::schedule_check_newly_generated_bitcoin_addresses_for_transactions
	 */
	public function test_schedule_check_newly_generated_bitcoin_addresses_for_transactions_already_scheduled(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$background_jobs_scheduler  = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );

		as_schedule_single_action(
			timestamp: time(),
			hook: Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK,
		);

		assert( true === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		/**
		 * @see Background_Jobs_Actions_Handler::schedule_check_newly_generated_bitcoin_addresses_for_transactions()
		 */
		$sut->schedule_check_newly_generated_bitcoin_addresses_for_transactions();

		$this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );
	}

	/**
	 * @covers ::schedule_check_newly_generated_bitcoin_addresses_for_transactions
	 */
	public function test_schedule_check_newly_generated_bitcoin_addresses_for_transactions_with_specific_datetime(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$background_jobs_scheduler  = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		$datetime = new DateTimeImmutable()->add( new DateInterval( 'P1D' ) );

		/**
		 * @see Background_Jobs_Actions_Handler::schedule_check_newly_generated_bitcoin_addresses_for_transactions()
		 */
		$sut->schedule_check_newly_generated_bitcoin_addresses_for_transactions( $datetime );

		$scheduled_actions = as_get_scheduled_actions( array( 'hook' => Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );
		/** @var ActionScheduler_Action $scheduled_action */
		$scheduled_action = array_pop( $scheduled_actions );

		/** @var ActionScheduler_Abstract_Schedule $scheduled_action_schedule */
		$scheduled_action_schedule = $scheduled_action->get_schedule();
		$result                    = $scheduled_action_schedule->get_date();

		$this->assertEquals( $datetime->getTimestamp(), $result?->getTimestamp() );
	}

	/**
	 * @covers ::schedule_check_assigned_bitcoin_address_for_transactions
	 * @covers ::schedule_check_assigned_addresses_for_transactions
	 */
	public function test_schedule_check_newly_assigned_bitcoin_address_for_transactions(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$background_jobs_scheduler  = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		/** @see Background_Jobs_Actions_Handler::schedule_check_assigned_bitcoin_address_for_transactions() */
		$sut->schedule_check_assigned_bitcoin_address_for_transactions();
	}

	/**
	 * @covers ::schedule_check_for_assigned_addresses_repeating_action
	 */
	public function test_schedule_check_for_assigned_addresses_repeating_action(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::once( true ),
			)
		);
		$background_jobs_scheduler  = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );

		/** @see Background_Jobs_Actions_Handler::schedule_check_for_assigned_addresses_repeating_action() */
		$sut->schedule_check_for_assigned_addresses_repeating_action();

		$this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}

	/**
	 * @covers ::schedule_check_for_assigned_addresses_repeating_action
	 */
	public function test_schedule_check_for_assigned_addresses_repeating_action_no_addresses_to_check(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::once( false ),
			)
		);

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		/** @see Background_Jobs_Scheduler_Interface::schedule_check_for_assigned_addresses_repeating_action() */
		$sut->schedule_check_for_assigned_addresses_repeating_action();

		$this->assertFalse( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}

	/**
	 * @covers ::schedule_check_for_assigned_addresses_repeating_action
	 */
	public function test_schedule_check_for_assigned_addresses_repeating_action_already_scheduled(): void {

		$logger                     = new ColorLogger();
		$api                        = $this->makeEmpty( API_Background_Jobs_Interface::class );
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::never(),
			)
		);
		$background_jobs_scheduler  = $this->makeEmpty( Background_Jobs_Scheduler_Interface::class );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		as_schedule_single_action(
			timestamp: new DateTimeImmutable()->getTimestamp(),
			hook: Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK,
		);

		$hooked_before    = as_get_scheduled_actions( array( 'hook' => Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
		$action_id_before = array_key_first( $hooked_before );

		/** @see Background_Jobs_Actions_Handler::schedule_check_for_assigned_addresses_repeating_action() */
		$sut->schedule_check_for_assigned_addresses_repeating_action();

		$hooked_after    = as_get_scheduled_actions( array( 'hook' => Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
		$action_id_after = array_key_first( $hooked_before );

		$this->assertCount( 1, $hooked_after );
		$this->assertEquals( $action_id_before, $action_id_after );
	}

	// assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );
	// $this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK ) );
}
