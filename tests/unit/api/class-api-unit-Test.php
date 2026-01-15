<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Check_Assigned_Addresses_For_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Update_Address_Transactions_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 */
class API_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		/** @phpstan-ignore argument.type */
		WP_Mock::passthruFunction( 'wp_json_encode', array( 'return' => fn( $value ) => json_encode( $value ) ) );
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
				'get_unknown_bitcoin_addresses' => Expected::once( $addresses ),
				'set_payment_address_status'    => Expected::once(),
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
}
