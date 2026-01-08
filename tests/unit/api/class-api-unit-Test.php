<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 */
class API_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	protected function get_sut(
		?Settings_Interface $settings = null,
		?LoggerInterface $logger = null,
		?Bitcoin_Wallet_Repository $bitcoin_wallet_repository = null,
		?Bitcoin_Address_Repository $bitcoin_address_repository = null,
		?Bitcoin_Transaction_Repository $bitcoin_transaction_repository = null,
		?Blockchain_API_Interface $blockchain_api = null,
		?Generate_Address_API_Interface $generate_address_api = null,
		?Exchange_Rate_API_Interface $exchange_rate_api = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduling = null,
	): API {
		$sut = new API(
			settings: $settings ?? $this->makeEmpty( Settings_Interface::class ),
			logger: $logger ?? new ColorLogger(),
			bitcoin_wallet_repository: $bitcoin_wallet_repository ?? $this->make( Bitcoin_Wallet_Repository::class ),
			bitcoin_address_repository: $bitcoin_address_repository ?? $this->make( Bitcoin_Address_Repository::class ),
			bitcoin_transaction_repository: $bitcoin_transaction_repository ?? $this->make( Bitcoin_Transaction_Repository::class ),
			blockchain_api: $blockchain_api ?? $this->makeEmpty( Blockchain_API_Interface::class ),
			generate_address_api: $generate_address_api ?? $this->makeEmpty( Generate_Address_API_Interface::class ),
			exchange_rate_api: $exchange_rate_api ?? $this->makeEmpty( Exchange_Rate_API_Interface::class ),
			background_jobs_scheduler: $background_jobs_scheduling ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class )
		);
		return $sut;
	}

	/**
	 * @covers ::check_new_addresses_for_transactions
	 * @covers ::check_addresses_for_transactions
	 * @covers ::update_address_transactions
	 */
	public function test_check_new_addresses_for_transactions(): void {

		$address = $this->makeEmpty(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'abcedf',
			)
		);

		$addresses = array(
			$address,
		);

		$updated_transaction = $this->make(
			Transaction::class,
			array(
				'get_block_time'   => new \DateTimeImmutable( 'now' ),
				'get_block_height' => 123,
			)
		);

		$updated_transactions = array(
			$updated_transaction,
		);

		$blockchain_api_mock = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_transactions_received' => Expected::once( $updated_transactions ),
			)
		);

		$bitcoin_address_repository_mock = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'get_unknown_bitcoin_addresses' => Expected::once( $addresses ),
			)
		);

		$bitcoin_transaction_mock = $this->makeEmpty(
			Bitcoin_Transaction::class,
		);

		$bitcoin_transaction_repository_mock = $this->makeEmpty(
			Bitcoin_Transaction_Repository::class,
			array(
				'save_new' => Expected::once( $bitcoin_transaction_mock ),
			)
		);

		$sut = $this->get_sut(
			bitcoin_address_repository: $bitcoin_address_repository_mock,
			bitcoin_transaction_repository: $bitcoin_transaction_repository_mock,
			blockchain_api: $blockchain_api_mock,
		);

		$result = $sut->check_new_addresses_for_transactions();
	}

	/**
	 * @covers ::update_address_transactions
	 */
	public function test_update_address_transactions(): void {

		$transaction = self::make(
			Transaction::class,
			array(
				'get_txid'         => 'transaction_from_api',
				'get_block_height' => 123,
				'get_block_time'   => new \DateTimeImmutable( 'now' ),
			)
		);

		$blockchain_api = self::makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_transactions_received' => Expected::once(
					function ( string $address ) use ( $transaction ): array {
						assert( 'xpub' === $address );
						return array( $transaction );
					}
				),
			)
		);

		$bitcoin_transaction = $this->make(
			Bitcoin_Transaction::class,
			array(
				'get_txid'    => 'transaction_from_wp_post',
				'get_post_id' => 567,
			)
		);

		$bitcoin_transaction_repository = self::makeEmpty(
			Bitcoin_Transaction_Repository::class,
			array(
				'save_new' => Expected::once(
					function (
						Transaction_Interface $transaction,
						Bitcoin_Address $address,
					) use ( $bitcoin_transaction ): Bitcoin_Transaction {
						return $bitcoin_transaction;
					}
				),
			)
		);

		$bitcoin_address_repository = self::makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'set_status' => Expected::once(),
			)
		);

		$sut = $this->get_sut(
			bitcoin_address_repository: $bitcoin_address_repository,
			bitcoin_transaction_repository: $bitcoin_transaction_repository,
			blockchain_api: $blockchain_api,
		);

		$address = self::make(
			Bitcoin_Address::class,
			array(
				'get_raw_address'  => Expected::once( 'xpub' ),
				'set_transactions' => Expected::once(),
				'get_status'       => Expected::once( Bitcoin_Address_Status::UNKNOWN ),
				'get_tx_ids'       => Expected::once( array() ),
			)
		);

		$result           = $sut->update_address_transactions( $address );
		$new_transactions = $result->get_new_transactions();
		$result_first     = array_shift(
			$new_transactions
		);

		$this->assertEquals( 'transaction_from_wp_post', $result_first?->get_txid() );
	}

	/**
	 * @covers ::get_exchange_rate
	 */
	public function test_get_exchange_rate_already_stored(): void {

		$sut = $this->get_sut();

		\WP_Mock::userFunction(
			'get_transient',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_exchange_rate_USD' ),
				'return' => Money::of( '65535', Currency::of( 'USD' ) )->jsonSerialize(),
			)
		);

		\WP_Mock::userFunction(
			'set_transient',
			array(
				'times' => 0,
			)
		);

		$result = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		self::assertEquals( '65535', $result?->abs()->getAmount()->toFloat() );
	}

	/**
	 * @covers ::get_exchange_rate
	 */
	public function test_get_exchange_rate_not_already_stored(): void {

		$exchange_rate_api = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once(
					function ( Currency $currency ): Money {
						assert( 'USD' === $currency->getCurrencyCode() );
						return Money::of( '54321', Currency::of( 'USD' ) );
					}
				),
			)
		);

		$sut = $this->get_sut(
			exchange_rate_api: $exchange_rate_api,
		);

		\WP_Mock::userFunction(
			'get_transient',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_exchange_rate_USD' ),
				'return' => false,
			)
		);

		\WP_Mock::userFunction(
			'set_transient',
			array(
				'times' => 1,
				'args'  => array(
					'bh_wp_bitcoin_gateway_exchange_rate_USD',
					array(
						'amount'   => '54321',
						'currency' => 'USD',
					),
					3600,
				),
			)
		);

		$result = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		self::assertEquals( '54321', $result?->abs()->getAmount()->toFloat() );
	}
}
