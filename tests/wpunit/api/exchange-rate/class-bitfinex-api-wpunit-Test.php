<?php
/**
 * Test get exchange rate.
 *
 * @package           brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate\Bitfinex_API
 */
class Bitfinex_API_Integration_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::get_exchange_rate
	 * @covers ::__construct
	 */
	public function test_query_api(): void {

		$logger = new ColorLogger();

		$sut = new Bitfinex_API( $logger );

		$request_response = array(
			'body'     => '[["tBTCUSD",40990,8.671964370000001,40991,9.711412020000001,-189,-0.0046,40990,2292.72205775,41499,40542]]',
			'response' =>
				array(
					'code'    => 200,
					'message' => 'OK',
				),
		);

		add_filter(
			'pre_http_request',
			fn() => $request_response
		);

		$result = $sut->get_exchange_rate( Currency::of( 'USD' ) );

		$this->assertEquals( '40990', $result->getAmount() );
	}
}
