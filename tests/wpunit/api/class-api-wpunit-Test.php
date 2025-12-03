<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\API
 */
class API_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	protected function get_sut(
		?Settings_Interface $settings = null,
		?LoggerInterface $logger = null,
		?Bitcoin_Wallet_Factory $bitcoin_wallet_factory = null,
		?Bitcoin_Address_Repository $bitcoin_address_repository = null,
		?Blockchain_API_Interface $blockchain_api = null,
		?Generate_Address_API_Interface $generate_address_api = null,
		?Exchange_Rate_API_Interface $exchange_rate_api = null,
		?Background_Jobs $background_jobs = null,
	): API {
		$settings                   = $settings ?? $this->makeEmpty( Settings_Interface::class );
		$logger                     = $logger ?? new ColorLogger();
		$bitcoin_wallet_factory     = $bitcoin_wallet_factory ?? $this->makeEmpty( Bitcoin_Wallet_Factory::class );
		$bitcoin_address_repository = $bitcoin_address_repository ?? $this->makeEmpty( Bitcoin_Address_Repository::class );
		$blockchain_api             = $blockchain_api ?? $this->makeEmpty( Blockchain_API_Interface::class );
		$generate_address_api       = $generate_address_api ?? $this->makeEmpty( Generate_Address_API_Interface::class );
		$exchange_rate_api          = $exchange_rate_api ?? $this->makeEmpty( Exchange_Rate_API_Interface::class );
		$background_jobs            = $background_jobs ?? $this->makeEmpty( Background_Jobs::class );

		$api = new API(
			$settings,
			$logger,
			$bitcoin_wallet_factory,
			$bitcoin_address_repository,
			$blockchain_api,
			$generate_address_api,
			$exchange_rate_api,
		);
		$api->set_background_jobs( $background_jobs );

		return $api;
	}

	/**
	 * @covers ::generate_new_addresses_for_wallet
	 */
	public function test_generate_addresses_for_gateway(): void {

		$this->markTestIncomplete();

		$test_xpub = 'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K';

		$wallet                 = $this->makeEmpty( Bitcoin_Wallet::class );
		$bitcoin_wallet_factory = $this->makeEmpty(
			Bitcoin_Wallet_Factory::class,
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
			bitcoin_wallet_factory: $bitcoin_wallet_factory,
			bitcoin_address_repository: $bitcoin_address_repository,
		);

		$result = $sut->generate_new_addresses_for_wallet( $test_xpub, 5 );
	}

	/**
	 * @covers ::convert_fiat_to_btc
	 */
	public function test_convert_fiat_to_btc(): void {

		$sut = $this->get_sut();

		$transient_name = 'bh_wp_bitcoin_gateway_exchange_rate_USD';
		add_filter(
			"pre_transient_{$transient_name}",
			function ( $retval, $transient ) {
				return Money::of( '23567', Currency::of( 'USD' ) )->jsonSerialize();
			},
			10,
			2
		);

		$result = $sut->convert_fiat_to_btc( Money::of( '10.99', 'USD' ) );

		$this->assertEquals( '0.00046633', $result->getAmount() );
	}
}
