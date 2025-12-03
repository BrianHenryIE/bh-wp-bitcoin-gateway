<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\My_Account_View_Order
 */
class My_Account_View_Order_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::print_status_instructions
	 * @covers ::__construct
	 */
	public function test_print_status_instructions(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once(
					function ( int $order_id ) {
						return true;
					}
				),
				'get_formatted_order_details'  => Expected::once(
					function ( $order ) {
						return array();
					}
				),
			)
		);

		$sut = new My_Account_View_Order( $api, $logger );

		$order = new \WC_Order();
		$order->set_payment_method( 'bitcoin' );
		$order_id = $order->save();

		add_filter(
			'wc_get_template',
			function (): string {
				throw new \Exception();
			}
		);

		$e = null;
		try {
			$sut->print_status_instructions( $order_id );
		} catch ( \Exception $exception ) {
			$e = $exception;
		}

		// Is there a better way to say wc_get_template was called?
		$this->assertNotNull( $e );
	}


	/**
	 * @covers ::print_status_instructions
	 */
	public function test_add_instructions_order_not_for_this_gateway(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once(
					function ( int $order_id ) {
						return false;
					}
				),
				'get_order_details'            => Expected::never(),
			)
		);

		$sut = new My_Account_View_Order( $api, $logger );

		$order_id = 123;

		$sut->print_status_instructions( $order_id );
	}
}
