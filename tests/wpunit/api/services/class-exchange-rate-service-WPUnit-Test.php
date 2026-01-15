<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service
 */
class Exchange_Rate_Service_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::get_exchange_rate
	 * @covers ::set_cached_exchange_rate
	 * @covers ::get_cached_exchange_rate
	 */
	public function test_get_exchange_rate(): void {

		$exchange_rate_api_mock = $this->makeEmpty(
			Exchange_Rate_API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once( Money::of( '89000', 'USD' ) ),
			)
		);
		$test_logger            = new ColorLogger();
		$sut                    = new Exchange_Rate_Service( $exchange_rate_api_mock, $test_logger );

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
		$test_logger            = new ColorLogger();
		$sut                    = new Exchange_Rate_Service( $exchange_rate_api_mock, $test_logger );

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
		$test_logger            = new ColorLogger();
		$sut                    = new Exchange_Rate_Service( $exchange_rate_api_mock, $test_logger );

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
}
