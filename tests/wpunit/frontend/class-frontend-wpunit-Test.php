<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Frontend;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Frontend\Frontend_Assets
 */
class Frontend_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::enqueue_scripts
	 */
	public function test_enqueue_scripts(): void {

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_version' => Expected::once(
					function () {
						return '1.0.0';
					}
				),
				'get_plugin_dir'     => Expected::once(
					function () {
						return codecept_root_dir();
					}
				),
			)
		);
		$api      = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once(
					function ( $order_id ) {
						return true;
					}
				),
				'get_formatted_order_details'  => Expected::once( array() ),
			)
		);

		$sut = new Frontend_Assets( $api, $settings, $logger );

		$order    = new \WC_Order();
		$order_id = $order->save();

		$GLOBALS['order-received'] = $order_id;

		$sut->enqueue_scripts();

		$this->assertTrue( wp_script_is( 'bh-wp-bitcoin-gateway-shortcode-thank-you' ) );

		// TODO: check the inline script is enqueued.
	}

	/**
	 * @covers ::enqueue_scripts
	 */
	public function test_enqueue_scripts_no_order_id(): void {

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_version' => Expected::never(),
			)
		);
		$api      = $this->makeEmpty(
			API_Interface::class,
			array( 'is_order_has_bitcoin_gateway' => Expected::never() )
		);

		unset( $GLOBALS['order-received'] );
		unset( $GLOBALS['view-order'] );

		$sut = new Frontend_Assets( $api, $settings, $logger );

		$sut->enqueue_scripts();
	}


	/**
	 * @covers ::enqueue_scripts
	 */
	public function test_enqueue_scripts_invalid_order_id(): void {

		$logger   = new ColorLogger();
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_version' => Expected::never(),
			)
		);
		$api      = $this->makeEmpty( API_WooCommerce_Interface::class );

		$GLOBALS['order-received'] = 123;

		$sut = new Frontend_Assets( $api, $settings, $logger );

		$sut->enqueue_scripts();
	}
}
