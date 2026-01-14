<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use Codeception\Stub\Expected;
use Psr\Log\LoggerInterface;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler
 */
class Background_Jobs_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

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
	 * @covers ::generate_new_addresses
	 * @covers ::__construct
	 */
	public function test_generate_new_adresses_action(): void {

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'generate_new_addresses' => Expected::once(
					function () {
						return array( $this->createMock( Addresses_Generation_Result::class ) );
					}
				),
			)
		);

		/** @var API_Background_Jobs_Interface $sut */
		$sut = $this->get_sut(
			api: $api,
		);

		/** @see Background_Jobs_Actions_Handler::generate_new_addresses() */
		$sut->generate_new_addresses();
	}

	/**
	 * @covers ::check_new_addresses_for_transactions
	 */
	public function test_check_new_addresses_for_transactions_action(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'check_new_addresses_for_transactions' => Expected::once(
					function () {
						return new Check_Assigned_Addresses_For_Transactions_Result( 1 );
					}
				),
			)
		);

		/** @var API_Background_Jobs_Interface $sut */
		$sut = $this->get_sut(
			api: $api,
			logger: $logger
		);

		/** @see Background_Jobs_Actions_Handler::check_new_addresses_for_transactions() */
		$sut->check_new_addresses_for_transactions();

		$this->assertTrue( $logger->hasDebugRecords() );

		// $this->markTestIncomplete( 'Assert the function logs a summary of the result.' );
	}

	/**
	 * @covers ::check_assigned_addresses_for_transactions
	 */
	public function test_check_assigned_addresses_for_transactions_action(): void {

		$logger              = new ColorLogger();
		$api                 = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'check_assigned_addresses_for_payment' => Expected::once(
					function () {
						return new Check_Assigned_Addresses_For_Transactions_Result( 2 );
					}
				),
			)
		);
		$wallet_service_mock = $this->makeEmpty(
			Bitcoin_Wallet_Service::class,
			array(
				'has_assigned_bitcoin_addresses' => Expected::once( false ),
			)
		);

		$sut = $this->get_sut(
			api: $api,
			wallet_service: $wallet_service_mock,
			logger: $logger
		);
		/** @see Background_Jobs_Actions_Handler::check_assigned_addresses_for_transactions() */
		$sut->check_assigned_addresses_for_transactions();

		$this->assertTrue( $logger->hasInfoRecords() );

		// $this->markTestIncomplete( 'Assert the function logs a summary of the result.' );
	}
}
