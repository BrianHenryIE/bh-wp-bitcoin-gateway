<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway_Blocks_Checkout_Support
 */
class Bitcoin_Gateway_Blocks_Checkout_Support_WPUnit_Test extends WPTestCase {

	protected function get_gateway( ?API_Interface $api = null, ?API_WooCommerce_Interface $api_woocommerce = null ): Bitcoin_Gateway {
		return new Bitcoin_Gateway(
			api: $api ?? $this->makeEmpty( API_Interface::class ),
			api_woocommerce: $api_woocommerce ?? $this->makeEmpty( API_WooCommerce_Interface::class ),
			plugin_settings: $this->makeEmpty( Settings_Interface::class ),
			logger: new ColorLogger(),
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::is_active
	 */
	public function test_is_active_returns_false_and_logs_when_gateway_throws(): void {

		$gateway = $this->make(
			Bitcoin_Gateway::class,
			array(
				'is_available' => Expected::once(
					function () {
						throw new \RuntimeException( 'Availability check failed' );
					}
				),
			)
		);
		$logger  = new ColorLogger();

		$sut = new Bitcoin_Gateway_Blocks_Checkout_Support( $gateway, $this->makeEmpty( API_Interface::class ), $this->makeEmpty( Settings_Interface::class ), $logger );

		$this->assertFalse( $sut->is_active() );
		$this->assertTrue( $logger->hasErrorThatContains( 'Availability check failed' ) );
	}

	/**
	 * @covers ::get_payment_method_data
	 * @covers ::get_exchange_rate_information
	 */
	public function test_payment_method_data_omits_exchange_rate_when_lookup_throws(): void {

		$api    = $this->makeEmpty(
			API_Interface::class,
			array(
				'get_exchange_rate' => Expected::once(
					function () {
						throw new \RuntimeException( 'Rate lookup exploded' );
					}
				),
			)
		);
		$logger = new ColorLogger();

		$sut = new Bitcoin_Gateway_Blocks_Checkout_Support( $this->get_gateway(), $api, $this->makeEmpty( Settings_Interface::class ), $logger );
		$sut->initialize();

		$data = $sut->get_payment_method_data();

		$this->assertSame( '', $data['exchange_rate_information'] );
		$this->assertTrue( $logger->hasErrorThatContains( 'Rate lookup exploded' ) );
	}
}
