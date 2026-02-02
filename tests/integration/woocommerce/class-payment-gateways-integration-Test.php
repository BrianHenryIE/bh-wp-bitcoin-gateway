<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\API\BH_WP_PSR_Logger;
use lucatume\WPBrowser\TestCase\WPTestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * @coversNothing
 */
class Payment_Gateway_Integration_Test extends WPTestCase {

	/**
	 * Test the correct logger is set when the plugin is loaded normally.
	 * i.e. NullLogger is set in the gateway constructor, but a real logger instance should be set by the time the
	 * gateway is used.
	 */
	public function test_logger(): void {

		$gateways = WC()->payment_gateways()->get_available_payment_gateways();

		$sut_gateway = null;

		foreach ( $gateways as $gateway ) {
			if ( $gateway instanceof Bitcoin_Gateway ) {
				$sut_gateway = $gateway;
			}
		}

		if ( is_null( $sut_gateway ) ) {
			$this->markTestIncomplete( 'Gateway probably unavailable because it has not been configured/generated addresses.' );
		}

		$reflection = new ReflectionClass( $sut_gateway );
		$property   = $reflection->getProperty( 'logger' );
		$logger     = $property->getValue( $sut_gateway );

		$this->assertFalse( $logger instanceof NullLogger );
		$this->assertInstanceOf( BH_WP_PSR_Logger::class, $logger );
	}
}
