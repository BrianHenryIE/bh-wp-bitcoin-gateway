<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Update_Exchange_Rate_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Addresses_Generation_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Exchange_Rate_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler
 */
class Background_Jobs_Actions_Handler_Unit_Test extends \Codeception\Test\Unit {

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
						return array(
							new Addresses_Generation_Result(
								wallet: $this->make( Bitcoin_Wallet::class ),
								new_addresses: array(), // @phpstan-ignore argument.type
							),
						);
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

		// TODO: Assert the function logs a summary of the result.
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

		// TODO: Assert the function logs a summary of the result.
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate(): void {

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'update_exchange_rate' => Expected::once(
					function () {
						return new Update_Exchange_Rate_Result(
							requested_exchange_rate_currency: 'USD',
							source: 'test',
							updated_exchange_rate: new Exchange_Rate_Service_Result(
								rate: Money::of( '90000', 'USD' ),
								api_classname: get_class( $this ),
								date_saved: new DateTimeImmutable(),
							),
						);
					}
				),
			)
		);

		$sut = $this->get_sut( $api );

		$sut->update_exchange_rate();
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_logs_debug_on_start(): void {

		$logger = new ColorLogger();

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'update_exchange_rate' => Expected::once(
					function () {
						return new Update_Exchange_Rate_Result(
							requested_exchange_rate_currency: 'USD',
							source: 'test',
							updated_exchange_rate: new Exchange_Rate_Service_Result(
								rate: Money::of( '90000', 'USD' ),
								api_classname: get_class( $this ),
								date_saved: new DateTimeImmutable(),
							),
						);
					}
				),
			)
		);

		$sut = $this->get_sut( api: $api, logger: $logger );

		$sut->update_exchange_rate();

		$this->assertTrue( $logger->hasDebugRecords() );
		$this->assertTrue( $logger->hasDebugThatContains( 'Starting update_exchange_rate() background job.' ) );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_logs_info_on_completion(): void {

		$logger = new ColorLogger();

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'update_exchange_rate' => Expected::once(
					function () {
						return new Update_Exchange_Rate_Result(
							requested_exchange_rate_currency: 'USD',
							source: 'test',
							updated_exchange_rate: new Exchange_Rate_Service_Result(
								rate: Money::of( '90000', 'USD' ),
								api_classname: get_class( $this ),
								date_saved: new DateTimeImmutable(),
							),
						);
					}
				),
			)
		);

		$sut = $this->get_sut( api: $api, logger: $logger );

		$sut->update_exchange_rate();

		$this->assertTrue( $logger->hasInfoRecords() );
		$this->assertTrue( $logger->hasInfoThatContains( 'Finished update_exchange_rate() background job.' ) );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_logs_currency_and_new_value(): void {

		$logger = new ColorLogger();

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'update_exchange_rate' => Expected::once(
					function () {
						return new Update_Exchange_Rate_Result(
							requested_exchange_rate_currency: 'EUR',
							source: 'woocommerce',
							updated_exchange_rate: new Exchange_Rate_Service_Result(
								rate: Money::of( '85000', 'EUR' ),
								api_classname: get_class( $this ),
								date_saved: new DateTimeImmutable(),
							),
						);
					}
				),
			)
		);

		$sut = $this->get_sut( api: $api, logger: $logger );

		$sut->update_exchange_rate();

		// Debug: check if we have info records.
		$this->assertTrue( $logger->hasInfoRecords(), 'Should have info records' );

		// Check that context contains expected values.
		$has_expected_context = false;
		foreach ( $logger->records as $record ) {
			/** @var array{level:string,context:array{currency?:string,new_value?:string,old_value?:string}} $record */
			if ( 'info' === $record['level']
				&& isset( $record['context']['currency'] )
				&& 'EUR' === $record['context']['currency']
				&& isset( $record['context']['new_value'] )
				&& '85000.00' === $record['context']['new_value'] ) {
				$has_expected_context = true;
				break;
			}
		}

		$this->assertTrue( $has_expected_context, 'Should log currency EUR and new_value 85000.00 in context' );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_logs_with_previous_cached_rate(): void {

		$logger = new ColorLogger();

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'update_exchange_rate' => Expected::once(
					function () {
						$previous_rate = new Exchange_Rate_Service_Result(
							rate: Money::of( '88000', 'USD' ),
							api_classname: get_class( $this ),
							date_saved: new DateTimeImmutable( '-1 hour' ),
						);

						return new Update_Exchange_Rate_Result(
							requested_exchange_rate_currency: 'USD',
							source: 'woocommerce',
							updated_exchange_rate: new Exchange_Rate_Service_Result(
								rate: Money::of( '90000', 'USD' ),
								api_classname: get_class( $this ),
								date_saved: new DateTimeImmutable(),
								previous_cached_exchange_rate: $previous_rate,
							),
						);
					}
				),
			)
		);

		$sut = $this->get_sut( api: $api, logger: $logger );

		$sut->update_exchange_rate();

		$assert_log = function ( $record ) {
			/** @var array{context:array{currency?:string,new_value?:string,old_value?:string}} $record */
			return isset( $record['context']['currency'] )
				&& 'USD' === $record['context']['currency']
				&& isset( $record['context']['new_value'] )
				&& '90000.00' === $record['context']['new_value']
				&& isset( $record['context']['old_value'] )
				&& '88000.00' === $record['context']['old_value'];
		};

		$this->assertTrue( $logger->hasInfoThatPasses( $assert_log ) );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_logs_without_previous_cached_rate(): void {

		$logger = new ColorLogger();

		$api = $this->makeEmpty(
			API_Background_Jobs_Interface::class,
			array(
				'update_exchange_rate' => Expected::once(
					function () {
						return new Update_Exchange_Rate_Result(
							requested_exchange_rate_currency: 'GBP',
							source: 'woocommerce',
							updated_exchange_rate: new Exchange_Rate_Service_Result(
								rate: Money::of( '75000', 'GBP' ),
								api_classname: get_class( $this ),
								date_saved: new DateTimeImmutable(),
								previous_cached_exchange_rate: null,
							),
						);
					}
				),
			)
		);

		$sut = $this->get_sut( api: $api, logger: $logger );

		$sut->update_exchange_rate();

		$assert_log = function ( array $record ): bool {
			/** @var array{context:array{currency?:string,new_value?:string,old_value?:string}} $record */
			return isset( $record['context']['currency'] )
				&& 'GBP' === $record['context']['currency']
				&& isset( $record['context']['new_value'] )
				&& '75000.00' === $record['context']['new_value']
				&& isset( $record['context']['old_value'] )
				&& '<null>' === $record['context']['old_value'];
		};

		$this->assertTrue( $logger->hasInfoThatPasses( $assert_log ) );
	}
}
