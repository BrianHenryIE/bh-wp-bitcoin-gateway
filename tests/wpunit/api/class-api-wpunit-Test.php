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
	 * @covers ::generate_new_addresses_for_wallet
	 */
	public function test_generate_addresses_for_gateway(): void {

		$this->markTestIncomplete();

		$test_xpub = 'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K';

		$wallet                    = $this->makeEmpty( Bitcoin_Wallet::class );
		$bitcoin_wallet_repository = $this->makeEmpty(
			Bitcoin_Wallet_Repository::class,
			array(
				'get_post_id_for_wallet' => Expected::once( 123 ),
				'get_by_post_id'         => Expected::once( $wallet ),
			)
		);

		$address                    = $this->makeEmpty( Bitcoin_Address::class );
		$bitcoin_address_repository = $this->makeEmpty(
			Bitcoin_Address_Repository::class,
			array(
				'save_new'       => Expected::exactly( 5, 123 ),
				'get_by_post_id' => Expected::exactly( 5, $address ),
			)
		);

		$sut = $this->get_sut(
			bitcoin_wallet_repository: $bitcoin_wallet_repository,
			bitcoin_address_repository: $bitcoin_address_repository,
		);

		$result = $sut->generate_new_addresses_for_wallet( $test_xpub, 5 );
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
