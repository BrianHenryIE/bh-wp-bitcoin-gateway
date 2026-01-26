<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 */
class API_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	protected function get_sut(
		?Settings_Interface $settings = null,
		?Exchange_Rate_Service $exchange_rate_service = null,
		?Bitcoin_Wallet_Service $bitcoin_wallet_service = null,
		?Payment_Service $payment_service = null,
		?Background_Jobs_Scheduler_Interface $background_jobs_scheduler = null,
		?LoggerInterface $logger = null,
	): API {
		return new API(
			settings: $settings ?? $this->makeEmpty( Settings_Interface::class ),
			exchange_rate_service: $exchange_rate_service ?? $this->makeEmpty( Exchange_Rate_Service::class ),
			wallet_service: $bitcoin_wallet_service ?? $this->make( Bitcoin_Wallet_Service::class ),
			payment_service: $payment_service ?? $this->make( Payment_Service::class ),
			background_jobs_scheduler: $background_jobs_scheduler ?? $this->makeEmpty( Background_Jobs_Scheduler_Interface::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * This is a very dumb function.
	 *
	 * @covers ::convert_fiat_to_btc
	 */
	public function test_convert_fiat_to_btc(): void {

		$exchange_rate_service = $this->makeEmpty(
			Exchange_Rate_Service::class,
			array(
				'convert_fiat_to_btc' => Expected::once(
					function ( Money $to_convert ): Money {
						$this->assertEquals( 'USD', $to_convert->getCurrency()->getCurrencyCode() );
						$this->assertEquals( 10.99, $to_convert->getAmount()->toFloat() );

						return Money::of( '0.00045', 'BTC' );
					}
				),
			)
		);

		$sut = $this->get_sut(
			exchange_rate_service: $exchange_rate_service
		);

		$result = $sut->convert_fiat_to_btc( Money::of( '10.99', 'USD' ) );

		$this->assertEquals( '0.00045', (string) $result->getAmount()->toFloat() );
	}
}
