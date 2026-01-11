<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use WC_Payment_Gateway;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Payment_Gateways
 */
class Payment_Gateways_Unit_Test extends \Codeception\Test\Unit {

	/**
	 * @covers ::add_to_woocommerce
	 */
	public function test_add_to_woocommerce(): void {

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty( Settings_Interface::class );
		$api      = $this->make( API::class );

		$sut = new Payment_Gateways( $api, $settings, $logger );

		$result = $sut->add_to_woocommerce( array() );

		$this->assertContains( Bitcoin_Gateway::class, $result );
	}

	/**
	 * @covers ::add_logger_to_gateways
	 * @covers ::__construct
	 */
	public function test_add_logger_to_gateways(): void {

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty( Settings_Interface::class );
		$api      = $this->make( API::class );

		$sut = new Payment_Gateways( $api, $settings, $logger );

		$gateways = array(
			$this->makeEmpty(
				Bitcoin_Gateway::class,
				array(
					'set_logger' => Expected::once(
						function ( $the_logger ) use ( $logger ) {
							assert( $the_logger === $logger );
						}
					),
				)
			),
			$this->makeEmpty(
				WC_Payment_Gateway::class,
				array(
					'set_logger' => Expected::never(),
				)
			),
		);

		$sut->add_logger_to_gateways( $gateways );
	}
}
