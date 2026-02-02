<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperInterface;
use Codeception\Stub\Expected;
use lucatume\WPBrowser\TestCase\WPTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service
 */
class Exchange_Rate_Service_WPUnit_Test extends WPTestCase {

	protected function get_sut(
		?Exchange_Rate_API_Interface $exchange_rate_api_mock = null,
		?JsonMapperInterface $json_mapper = null,
		?LoggerInterface $logger = null,
	): Exchange_Rate_Service {
		return new Exchange_Rate_Service(
			$exchange_rate_api_mock ?? $this->makeEmpty( Exchange_Rate_API_Interface::class ),
			json_mapper: $json_mapper ?? new JsonMapper_Helper()->build(),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * @covers ::get_exchange_rate
	 * @covers ::set_cached_exchange_rate
	 * @covers ::__construct
	 * @covers ::get_transient_name
	 * @covers ::fetch_exchange_rate
	 */
	public function test_get_exchange_rate(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once( Money::of( '89000', 'USD' ) ),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$result1 = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		$this->assertEquals( 'USD', $result1?->getCurrency()->getCurrencyCode() );
		$this->assertEquals( '89000.00', $result1?->getAmount()->__toString() );

		$cached = get_transient( 'bh_wp_bitcoin_gateway_exchange_rate_USD' );
		$this->assertNotEmpty( $cached );
		// @phpstan-ignore argument.type
		$this->assertIsObject( json_decode( $cached ) );

		$result2 = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		$this->assertEquals( 'USD', $result2?->getCurrency()->getCurrencyCode() );
		$this->assertEquals( '89000.00', $result2?->getAmount()->__toString() );
	}

	/**
	 * @covers ::get_exchange_rate
	 * @covers ::get_cached_exchange_rate
	 */
	public function test_get_cached_exchange_rate(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::never(),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$json = <<<'EOD'
				{
				  "rate": {
					"amount": "89100.00",
					"currency": "USD"
				  },
				  "api_classname": "Exchange_Rate_API_Implementation",
				  "date_saved": {
					"date": "2026-01-14 21:53:05.426705",
					"timezone_type": 3,
					"timezone": "UTC"
				  }
				}
				EOD;

		set_transient( 'bh_wp_bitcoin_gateway_exchange_rate_USD', $json, HOUR_IN_SECONDS );

		$result = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		$this->assertEquals( 'USD', $result?->getCurrency()->getCurrencyCode() );
		$this->assertEquals( '89100.00', $result?->getAmount()->__toString() );
	}

	/**
	 * @covers ::convert_fiat_to_btc
	 */
	public function test_convert_fiat_to_btc(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::never(),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$json = <<<'EOD'
				{
				  "rate": {
					"amount": "90000.00",
					"currency": "USD"
				  },
				  "api_classname": "Exchange_Rate_API_Implementation",
				  "date_saved": {
					"date": "2026-01-14 21:53:05.426705",
					"timezone_type": 3,
					"timezone": "UTC"
				  }
				}
				EOD;

		set_transient( 'bh_wp_bitcoin_gateway_exchange_rate_USD', $json, HOUR_IN_SECONDS );

		$result = $sut->convert_fiat_to_btc( Money::of( '45000.00', 'USD' ) );

		$this->assertTrue(
			$result->isEqualTo( Money::of( 0.5, 'BTC' ) ),
			$result->getAmount()->__toString()
		);
	}

	/**
	 * @covers ::convert_fiat_to_btc
	 */
	public function test_convert_fiat_to_btc_throws_exception_when_no_rate_available(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once(
					function () {
						throw new \BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\Rate_Limit_Exception(
							new \DateTimeImmutable()
						);
					}
				),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$this->expectException( \BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'No exchange rate available' );

		$sut->convert_fiat_to_btc( Money::of( '100.00', 'USD' ) );
	}

	/**
	 * @covers ::convert_fiat_to_btc
	 */
	public function test_convert_fiat_to_btc_with_eur(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::never(),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$json = <<<'EOD'
				{
				  "rate": {
					"amount": "85000.00",
					"currency": "EUR"
				  },
				  "api_classname": "Exchange_Rate_API_Implementation",
				  "date_saved": {
					"date": "2026-01-14 21:53:05.426705",
					"timezone_type": 3,
					"timezone": "UTC"
				  }
				}
				EOD;

		set_transient( 'bh_wp_bitcoin_gateway_exchange_rate_EUR', $json, HOUR_IN_SECONDS );

		$result = $sut->convert_fiat_to_btc( Money::of( '42500.00', 'EUR' ) );

		$this->assertTrue(
			$result->isEqualTo( Money::of( 0.5, 'BTC' ) ),
			$result->getAmount()->__toString()
		);
	}

	/**
	 * @covers ::convert_fiat_to_btc
	 */
	public function test_convert_fiat_to_btc_with_small_amount(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::never(),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$json = <<<'EOD'
				{
				  "rate": {
					"amount": "90000.00",
					"currency": "USD"
				  },
				  "api_classname": "Exchange_Rate_API_Implementation",
				  "date_saved": {
					"date": "2026-01-14 21:53:05.426705",
					"timezone_type": 3,
					"timezone": "UTC"
				  }
				}
				EOD;

		set_transient( 'bh_wp_bitcoin_gateway_exchange_rate_USD', $json, HOUR_IN_SECONDS );

		$result = $sut->convert_fiat_to_btc( Money::of( '9.00', 'USD' ) );

		$this->assertEquals( 'BTC', $result->getCurrency()->getCurrencyCode() );
		$this->assertTrue( $result->getAmount()->isPositive() );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once( Money::of( '91000', 'USD' ) ),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$result = $sut->update_exchange_rate( Currency::of( 'USD' ) );

		$this->assertInstanceOf( Results\Exchange_Rate_Service_Result::class, $result );
		$this->assertEquals( 'USD', $result->rate->getCurrency()->getCurrencyCode() );
		$this->assertEquals( '91000.00', $result->rate->getAmount()->__toString() );
		$this->assertNull( $result->previous_cached_exchange_rate );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_with_previous_cached_rate(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once( Money::of( '92000', 'USD' ) ),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$json = <<<'EOD'
				{
				  "rate": {
					"amount": "89000.00",
					"currency": "USD"
				  },
				  "api_classname": "Exchange_Rate_API_Implementation",
				  "date_saved": {
					"date": "2026-01-14 20:00:00.000000",
					"timezone_type": 3,
					"timezone": "UTC"
				  }
				}
				EOD;

		set_transient( 'bh_wp_bitcoin_gateway_exchange_rate_USD', $json, HOUR_IN_SECONDS );

		$result = $sut->update_exchange_rate( Currency::of( 'USD' ) );

		$this->assertInstanceOf( Results\Exchange_Rate_Service_Result::class, $result );
		$this->assertEquals( '92000.00', $result->rate->getAmount()->__toString() );
		$this->assertNotNull( $result->previous_cached_exchange_rate );
		$this->assertEquals( '89000.00', $result->previous_cached_exchange_rate->rate->getAmount()->__toString() );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_caches_result(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once( Money::of( '93000', 'GBP' ) ),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$result = $sut->update_exchange_rate( Currency::of( 'GBP' ) );

		$cached = get_transient( 'bh_wp_bitcoin_gateway_exchange_rate_GBP' );
		$this->assertNotEmpty( $cached );
		$this->assertIsString( $cached );

		/** @var false|object{rate:object{amount:string}} $decoded */
		$decoded = json_decode( $cached );
		$this->assertIsObject( $decoded );
		$this->assertEquals( '93000.00', $decoded->rate->amount );
	}

	/**
	 * @covers ::get_exchange_rate
	 */
	public function test_get_exchange_rate_with_multiple_currencies(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::exactly(
					3,
					fn( Currency $currency ) => match ( $currency->getCurrencyCode() ) {
							'USD' => Money::of( '90000', 'USD' ),
							'EUR' => Money::of( '85000', 'EUR' ),
							'GBP' => Money::of( '75000', 'GBP' ),
							default => throw new \Exception( 'Unexpected currency' ),
						}
				),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$usd_result = $sut->get_exchange_rate( Currency::of( 'USD' ) );
		$eur_result = $sut->get_exchange_rate( Currency::of( 'EUR' ) );
		$gbp_result = $sut->get_exchange_rate( Currency::of( 'GBP' ) );

		$this->assertEquals( '90000.00', $usd_result?->getAmount()->__toString() );
		$this->assertEquals( '85000.00', $eur_result?->getAmount()->__toString() );
		$this->assertEquals( '75000.00', $gbp_result?->getAmount()->__toString() );
	}

	/**
	 * @covers ::get_exchange_rate
	 */
	public function test_get_exchange_rate_returns_cached_on_second_call(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once( Money::of( '94000', 'USD' ) ),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$first_result  = $sut->get_exchange_rate( Currency::of( 'USD' ) );
		$second_result = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		$this->assertEquals( $first_result?->getAmount()->__toString(), $second_result?->getAmount()->__toString() );
		$this->assertEquals( '94000.00', $second_result?->getAmount()->__toString() );
	}

	/**
	 * @covers ::convert_fiat_to_btc
	 */
	public function test_convert_fiat_to_btc_with_large_amount(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::never(),
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$json = <<<'EOD'
				{
				  "rate": {
					"amount": "90000.00",
					"currency": "USD"
				  },
				  "api_classname": "Exchange_Rate_API_Implementation",
				  "date_saved": {
					"date": "2026-01-14 21:53:05.426705",
					"timezone_type": 3,
					"timezone": "UTC"
				  }
				}
				EOD;

		set_transient( 'bh_wp_bitcoin_gateway_exchange_rate_USD', $json, HOUR_IN_SECONDS );

		$result = $sut->convert_fiat_to_btc( Money::of( '900000.00', 'USD' ) );

		$this->assertEquals( 'BTC', $result->getCurrency()->getCurrencyCode() );
		$this->assertTrue( $result->isEqualTo( Money::of( 10, 'BTC' ) ) );
	}

	/**
	 * @covers ::update_exchange_rate
	 */
	public function test_update_exchange_rate_with_different_currencies(): void {

		$call_count             = 0;
		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => function ( Currency $currency ) use ( &$call_count ) {
					$call_count++;
					return match ( $currency->getCurrencyCode() ) {
						'USD' => Money::of( '95000', 'USD' ),
						'EUR' => Money::of( '86000', 'EUR' ),
						default => throw new \Exception( 'Unexpected currency' ),
					};
				},
			)
		);
		$sut                    = $this->get_sut( $exchange_rate_api_mock );

		$usd_result = $sut->update_exchange_rate( Currency::of( 'USD' ) );
		$eur_result = $sut->update_exchange_rate( Currency::of( 'EUR' ) );

		$this->assertEquals( 2, $call_count );
		$this->assertEquals( '95000.00', $usd_result->rate->getAmount()->__toString() );
		$this->assertEquals( '86000.00', $eur_result->rate->getAmount()->__toString() );
	}
}
