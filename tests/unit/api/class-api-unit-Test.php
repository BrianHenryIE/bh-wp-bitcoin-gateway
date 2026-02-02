<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Update_Exchange_Rate_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Exchange_Rate_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 */
class API_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		/** @phpstan-ignore argument.type */
		WP_Mock::passthruFunction( 'wp_json_encode', array( 'return' => json_encode( ... ) ) );
	}

	protected function tearDown(): void {
		parent::tearDown();
		WP_Mock::tearDown();
	}

	protected function get_sut(
		?Settings_Interface $settings = null,
		?Exchange_Rate_Service $exchange_rate_service = null,
		?Bitcoin_Wallet_Service $wallet_service = null,
		?Payment_Service $payment_service = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduler = null,
		?LoggerInterface $logger = null,
	): API {
		return new API(
			settings: $settings ?? $this->makeEmpty( Settings_Interface::class ),
			exchange_rate_service: $exchange_rate_service ?? $this->makeEmpty( Exchange_Rate_Service::class ),
			wallet_service: $wallet_service ?? $this->make( Bitcoin_Wallet_Service::class ),
			payment_service: $payment_service ?? $this->make( Payment_Service::class ),
			background_jobs_scheduler: $background_jobs_scheduler ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * @covers ::check_new_addresses_for_transactions
	 * @covers ::check_addresses_for_transactions
	 */
	public function test_check_new_addresses_for_transactions(): void {

		$address = $this->makeEmpty(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'abcedf',
				'get_status'      => Bitcoin_Address_Status::UNKNOWN,
			)
		);

		$addresses = array(
			$address,
		);

		$updated_transaction = new Transaction(
			tx_id: 'tx123',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 100000000, 'BTC' ),
					scriptpubkey_address: 'raw_address',
				),
			),
			block_height: 123,
		);

		$updated_transactions = array(
			$updated_transaction,
		);

		$wallet_service_mock = $this->make(
			Bitcoin_Wallet_Service::class,
			array(
				'get_unknown_bitcoin_addresses'     => Expected::once( $addresses ),
				'set_payment_address_status'        => Expected::once(),
				'update_address_transactions_posts' => Expected::once(),
			)
		);

		$update_address_transactions_result = new Update_Address_Transactions_Result(
			queried_address: $address,
			known_tx_ids_before: null,
			all_transactions: $updated_transactions
		);

		$payment_service_mock = $this->make(
			Payment_Service::class,
			array(
				'update_address_transactions' => Expected::once( $update_address_transactions_result ),
			)
		);

		$sut = $this->get_sut(
			wallet_service: $wallet_service_mock,
			payment_service: $payment_service_mock,
		);

		$result = $sut->check_new_addresses_for_transactions();

		$this->assertEquals( 1, $result->count );
	}

	/**
	 * Long-term, the WooCommerce part should be abstracted away.
	 *
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_with_woocommerce(): void {

		WP_Mock::userFunction(
			'get_woocommerce_currency',
			array(
				'times'  => 1,
				'return' => 'USD',
			)
		);

		$exchange_rate_service_result = new Exchange_Rate_Service_Result(
			rate: Money::of( '91000', 'USD' ),
			api_classname: 'Test_API',
			date_saved: new DateTimeImmutable(),
		);

		$exchange_rate_service_mock = $this->makeEmpty(
			Exchange_Rate_Service::class,
			array(
				'update_exchange_rate' => Expected::once(
					function ( Currency $currency ) use ( $exchange_rate_service_result ) {
						$this->assertEquals( 'USD', $currency->getCurrencyCode() );
						return $exchange_rate_service_result;
					}
				),
			)
		);

		$logger = new ColorLogger();

		$sut = $this->get_sut(
			exchange_rate_service: $exchange_rate_service_mock,
			logger: $logger
		);

		$result = $sut->update_exchange_rate();

		$this->assertInstanceOf( Update_Exchange_Rate_Result::class, $result );
		$this->assertEquals( 'USD', $result->requested_exchange_rate_currency );
		$this->assertEquals( 'woocommerce', $result->source );
		$this->assertEquals( '91000.00', $result->rate->getAmount()->__toString() );

		$this->assertTrue( $logger->hasDebugThatContains( 'Exchange rate updated for' ) );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_with_eur_currency(): void {

		WP_Mock::userFunction(
			'get_woocommerce_currency',
			array(
				'times'  => 1,
				'return' => 'EUR',
			)
		);

		$exchange_rate_service_result = new Exchange_Rate_Service_Result(
			rate: Money::of( '85000', 'EUR' ),
			api_classname: 'Test_API',
			date_saved: new DateTimeImmutable(),
		);

		$exchange_rate_service_mock = $this->makeEmpty(
			Exchange_Rate_Service::class,
			array(
				'update_exchange_rate' => Expected::once(
					function ( Currency $currency ) use ( $exchange_rate_service_result ) {
						$this->assertEquals( 'EUR', $currency->getCurrencyCode() );
						return $exchange_rate_service_result;
					}
				),
			)
		);

		$sut = $this->get_sut(
			exchange_rate_service: $exchange_rate_service_mock
		);

		$result = $sut->update_exchange_rate();

		$this->assertEquals( 'EUR', $result->requested_exchange_rate_currency );
		$this->assertEquals( 'woocommerce', $result->source );
		$this->assertEquals( '85000.00', $result->rate->getAmount()->__toString() );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_includes_previous_cached_rate(): void {

		WP_Mock::userFunction(
			'get_woocommerce_currency',
			array(
				'times'  => 1,
				'return' => 'USD',
			)
		);

		$previous_rate = new Exchange_Rate_Service_Result(
			rate: Money::of( '89000', 'USD' ),
			api_classname: 'Test_API',
			date_saved: new DateTimeImmutable( '-1 hour' ),
		);

		$exchange_rate_service_result = new Exchange_Rate_Service_Result(
			rate: Money::of( '93000', 'USD' ),
			api_classname: 'Test_API',
			date_saved: new DateTimeImmutable(),
			previous_cached_exchange_rate: $previous_rate,
		);

		$exchange_rate_service_mock = $this->makeEmpty(
			Exchange_Rate_Service::class,
			array(
				'update_exchange_rate' => Expected::once( $exchange_rate_service_result ),
			)
		);

		$sut = $this->get_sut(
			exchange_rate_service: $exchange_rate_service_mock
		);

		$result = $sut->update_exchange_rate();

		$this->assertNotNull( $result->previous_cached_exchange_rate );
		$this->assertEquals( '89000.00', $result->previous_cached_exchange_rate->rate->getAmount()->__toString() );
		$this->assertEquals( '93000.00', $result->rate->getAmount()->__toString() );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_logs_currency(): void {

		WP_Mock::userFunction(
			'get_woocommerce_currency',
			array(
				'times'  => 1,
				'return' => 'GBP',
			)
		);

		$exchange_rate_service_result = new Exchange_Rate_Service_Result(
			rate: Money::of( '75000', 'GBP' ),
			api_classname: 'Test_API',
			date_saved: new DateTimeImmutable(),
		);

		$exchange_rate_service_mock = $this->makeEmpty(
			Exchange_Rate_Service::class,
			array(
				'update_exchange_rate' => Expected::once( $exchange_rate_service_result ),
			)
		);

		$logger = new ColorLogger();

		$sut = $this->get_sut(
			exchange_rate_service: $exchange_rate_service_mock,
			logger: $logger
		);

		$result = $sut->update_exchange_rate();

		$assert_log = function ( array $record ): bool {
			/** @var array{context:array{currency?:string}} $record */
			return isset( $record['context']['currency'] )
				&& 'GBP' === $record['context']['currency'];
		};

		$this->assertTrue( $logger->hasDebugThatPasses( $assert_log ) );
	}
}
