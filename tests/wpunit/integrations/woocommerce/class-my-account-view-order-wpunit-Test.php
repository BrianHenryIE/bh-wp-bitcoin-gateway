<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
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
				'get_bitcoin_order'           => function () {
					return $this->make( WC_Bitcoin_Order::class );
				},
				'get_formatted_order_details' => Expected::once(
					fn( $order ) => array()
				),
			)
		);

		$sut = new My_Account_View_Order( $api, $logger );

		// Record that the template was requested, then abort rendering it (the exception is caught and logged by the sut).
		$template_requested = false;
		add_filter(
			'wc_get_template',
			function () use ( &$template_requested ): string {
				$template_requested = true;
				throw new \Exception( 'template requested' );
			}
		);

		$sut->print_status_instructions( 123 );

		$this->assertTrue( $template_requested );
	}


	/**
	 * @covers ::print_status_instructions
	 */
	public function test_add_instructions_order_not_for_this_gateway(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order' => Expected::once( fn() => null ),
				'get_order_details' => Expected::never(),
			)
		);

		$sut = new My_Account_View_Order( $api, $logger );

		$order_id = 123;

		$sut->print_status_instructions( $order_id );
	}

	/**
	 * Loading the order was previously outside the try/catch, and the catch only caught `Exception`, so an
	 * `Error` (e.g. from the details formatter) was a fatal on a customer-facing page.
	 *
	 * @covers ::print_status_instructions
	 */
	public function test_print_status_instructions_logs_and_does_not_throw_on_error(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order' => Expected::once(
					function () {
						throw new \TypeError( 'Call to a member function getTimestamp() on null' );
					}
				),
			)
		);

		$sut = new My_Account_View_Order( $api, $logger );

		$order_id = 123;

		$sut->print_status_instructions( $order_id );

		$this->assertTrue( $logger->hasWarningThatContains( 'getTimestamp() on null' ) );
	}
}
