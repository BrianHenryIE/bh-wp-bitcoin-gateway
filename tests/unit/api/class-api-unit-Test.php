<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;

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

	/**
	 * @covers ::update_address_transactions
	 */
	public function test_update_address_transactions(): void {

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty( Settings_Interface::class );

		$bitcoin_wallet_factory     = $this->makeEmpty( Bitcoin_Wallet_Repository::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );

		$transaction = self::makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid' => 'transaction',
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

		$generate_address_api = $this->makeEmpty( Generate_Address_API_Interface::class );
		$exchange_rate_api    = $this->makeEmpty( Exchange_Rate_API_Interface::class );

		$background_jobs = $this->makeEmpty( Background_Jobs::class );

		$sut = new API( $settings, $logger, $bitcoin_wallet_factory, $bitcoin_address_repository, $blockchain_api, $generate_address_api, $exchange_rate_api, $background_jobs );

		$address = self::make(
			Bitcoin_Address::class,
			array(
				'get_raw_address'  => Expected::once( 'xpub' ),
				'set_transactions' => Expected::once(),
			)
		);

		$result       = $sut->update_address_transactions( $address );
		$result_first = array_shift( $result );

		$this->assertEquals( 'transaction', $result_first?->get_txid() );
	}

	/**
	 * @covers ::get_exchange_rate
	 */
	public function test_get_exchange_rate_already_stored(): void {

		$logger                     = new ColorLogger();
		$settings                   = $this->makeEmpty( Settings_Interface::class );
		$bitcoin_wallet_factory     = $this->makeEmpty( Bitcoin_Wallet_Repository::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$blockchain_api             = $this->makeEmpty( Blockchain_API_Interface::class );
		$generate_address_api       = $this->makeEmpty( Generate_Address_API_Interface::class );
		$exchange_rate_api          = $this->makeEmpty( Exchange_Rate_API_Interface::class );
		$background_jobs            = $this->makeEmpty( Background_Jobs::class );

		$sut = new API( $settings, $logger, $bitcoin_wallet_factory, $bitcoin_address_repository, $blockchain_api, $generate_address_api, $exchange_rate_api, $background_jobs );
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

		$logger                     = new ColorLogger();
		$settings                   = $this->makeEmpty( Settings_Interface::class );
		$bitcoin_wallet_factory     = $this->makeEmpty( Bitcoin_Wallet_Repository::class );
		$bitcoin_address_repository = $this->makeEmpty( Bitcoin_Address_Repository::class );
		$blockchain_api             = $this->makeEmpty( Blockchain_API_Interface::class );
		$generate_address           = $this->makeEmpty( Generate_Address_API_Interface::class );
		$exchange_rate_api          = $this->makeEmpty(
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

		$background_jobs = $this->makeEmpty( Background_Jobs::class );

		$sut = new API( $settings, $logger, $bitcoin_wallet_factory, $bitcoin_address_repository, $blockchain_api, $generate_address, $exchange_rate_api, $background_jobs );

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
