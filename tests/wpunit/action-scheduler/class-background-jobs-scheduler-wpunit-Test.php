<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use ActionScheduler_Abstract_Schedule;
use ActionScheduler_Action;
use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use Codeception\Stub\Expected;
use DateInterval;
use DateTimeImmutable;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler
 */
class Background_Jobs_Scheduler_WPUnit_Test extends WPTestCase {

	protected function setUp(): void {
		parent::setUp();

		as_unschedule_all_actions( Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK );
		as_unschedule_all_actions( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK );
		as_unschedule_all_actions( Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK );
	}

	/**
	 * @covers ::schedule_recurring_ensure_unused_addresses
	 */
	public function test_schedule_ensure_unused_addresses(): void {
		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK ) );

		$sut->schedule_recurring_ensure_unused_addresses();

		$this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK ) );

		$logger->hasInfo( 'Background_Jobs schedule_ensure_unused_addresses hourly job added.' );
	}

	/**
	 * @covers ::schedule_recurring_ensure_unused_addresses
	 */
	public function test_schedule_ensure_unused_addresses_already_scheduled(): void {
		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		as_schedule_recurring_action(
			timestamp: time(),
			interval_in_seconds: constant( 'HOUR_IN_SECONDS' ),
			hook: Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK,
			unique: true,
		);

		$sut->schedule_recurring_ensure_unused_addresses();

		$this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK ) );

		$logger->hasDebug( 'Background_Jobs schedule_ensure_unused_addresses already scheduled.' );
	}

	/**
	 * @covers ::schedule_recurring_ensure_unused_addresses
	 */
	public function test_schedule_ensure_unused_addresses_failure(): void {
		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		// FAIL!
		add_filter( 'pre_as_schedule_recurring_action', fn() => 0 );

		$sut->schedule_recurring_ensure_unused_addresses();

		$this->assertFalse( as_has_scheduled_action( Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK ) );

		$logger->hasDebug( 'Background_Jobs schedule_ensure_unused_addresses failed.' );
	}

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
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

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
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

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
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

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
	 * @covers ::schedule_single_check_assigned_addresses_for_transactions
	 */
	public function test_schedule_check_newly_assigned_bitcoin_address_for_transactions(): void {

		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		/** @see Background_Jobs_Actions_Handler::schedule_check_assigned_bitcoin_address_for_transactions() */
		$sut->schedule_single_check_assigned_addresses_for_transactions();

		$this->markTestIncomplete();
	}

	/**
	 * @covers ::schedule_single_check_assigned_addresses_for_transactions
	 */
	public function test_schedule_single_check_assigned_addresses_for_transactions(): void {

		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::once( true ),
			)
		);

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		assert( false === as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );

		/** @see Background_Jobs_Actions_Handler::schedule_check_for_assigned_addresses_repeating_action() */
		$sut->schedule_single_check_assigned_addresses_for_transactions();

		$this->assertTrue( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}

	/**
	 * @covers ::schedule_single_check_assigned_addresses_for_transactions
	 */
	public function test_schedule_single_check_assigned_addresses_for_transactions_no_addresses_to_check(): void {

		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::once( false ),
			)
		);

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		/** @see Background_Jobs_Scheduler_Interface::schedule_single_check_assigned_addresses_for_transactions() */
		$sut->schedule_single_check_assigned_addresses_for_transactions();

		$this->assertFalse( as_has_scheduled_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
	}

	/**
	 * @covers ::schedule_single_check_assigned_addresses_for_transactions
	 */
	public function test_schedule_single_check_assigned_addresses_for_transactions_already_scheduled(): void {

		$logger                     = new ColorLogger();
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::never(),
			)
		);

		/** @var Background_Jobs_Scheduler_Interface $sut */
		$sut = new Background_Jobs_Scheduler( $bitcoin_address_repository, $logger );

		as_schedule_single_action(
			timestamp: new DateTimeImmutable()->getTimestamp(),
			hook: Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK,
		);

		$hooked_before    = as_get_scheduled_actions( array( 'hook' => Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
		$action_id_before = array_key_first( $hooked_before );

		/** @see Background_Jobs_Actions_Handler::schedule_single_check_assigned_addresses_for_transactions() */
		$sut->schedule_single_check_assigned_addresses_for_transactions();

		$hooked_after    = as_get_scheduled_actions( array( 'hook' => Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK ) );
		$action_id_after = array_key_first( $hooked_before );

		$this->assertCount( 1, $hooked_after );
		$this->assertEquals( $action_id_before, $action_id_after );
	}
}
