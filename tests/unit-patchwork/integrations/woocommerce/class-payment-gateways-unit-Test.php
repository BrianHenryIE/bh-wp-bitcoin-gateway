<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\BH_WP_Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use WC_Payment_Gateway;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Payment_Gateways
 */
class Payment_Gateways_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * @covers ::add_to_woocommerce
	 */
	public function test_add_to_woocommerce(): void {

		\Patchwork\redefine(
			array( Bitcoin_Gateway::class, 'init_form_fields' ),
			function () {}
		);
		\WP_Mock::passthruFunction( 'plugins_url' );
		\Patchwork\redefine(
			array( Bitcoin_Gateway::class, 'init_settings' ),
			function () {}
		);

		$logger          = new ColorLogger();
		$settings        = $this->makeEmpty( Settings_Interface::class );
		$api             = $this->makeEmpty( API_Interface::class );
		$api_woocommerce = $this->makeEmpty( API_WooCommerce_Interface::class );

		$sut = new Payment_Gateways( $api, $api_woocommerce, $settings, $logger );

		$result = $sut->add_to_woocommerce( array() );

		$this->assertInstanceOf( Bitcoin_Gateway::class, $result[0] );
	}
}
