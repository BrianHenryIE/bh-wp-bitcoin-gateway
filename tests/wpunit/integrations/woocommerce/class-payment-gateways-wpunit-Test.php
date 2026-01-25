<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Payment_Gateways
 */
class Payment_Gateways_WPUnit_Test extends WPTestCase {

	/**
	 * @covers ::add_to_woocommerce
	 */
	public function test_add_to_woocommerce(): void {

		$logger          = new ColorLogger();
		$settings        = $this->makeEmpty( Settings_Interface::class );
		$api             = $this->makeEmpty( API_Interface::class );
		$api_woocommerce = $this->makeEmpty( API_WooCommerce_Interface::class );

		$sut = new Payment_Gateways( api:$api, api_woocommerce: $api_woocommerce, settings: $settings, logger:  $logger );

		$result = $sut->add_to_woocommerce( array() );

		$this->assertInstanceOf( Bitcoin_Gateway::class, $result[0] );
	}
}
