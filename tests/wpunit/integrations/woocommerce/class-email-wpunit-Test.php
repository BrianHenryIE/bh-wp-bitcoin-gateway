<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Email
 */
class Email_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::print_instructions
	 */
	public function test_print_instructions(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_bitcoin_gateway' => Expected::once(
					function ( $gateway_id ) {
						return true;
					}
				),
			)
		);

		$sut = new Email( $api, $logger );

		$order = new WC_Order();
		$order->save();
		$sent_to_admin = false;
		$plain_text    = false;

		add_filter(
			'wc_get_template',
			function (): string {
				throw new \Exception();
			}
		);

		$e = null;
		try {
			$sut->print_instructions( $order, $sent_to_admin, $plain_text );
		} catch ( \Exception $exception ) {
			$e = $exception;
		}

		// Is there a better way to say wc_get_template was called?
		$this->assertNotNull( $e );
	}


	/**
	 * @covers ::print_instructions
	 * @covers ::__construct
	 */
	public function test_print_instructions_admin_return_early(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_Interface::class,
			array( 'is_bitcoin_gateway' => Expected::never() )
		);

		$sut = new Email( $api, $logger );

		$order         = new WC_Order();
		$sent_to_admin = true;
		$plain_text    = false;

		$sut->print_instructions( $order, $sent_to_admin, $plain_text );
	}


	/**
	 * @covers ::print_instructions
	 */
	public function test_print_instructions_not_bitcoin_gateway(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_bitcoin_gateway' => Expected::once(
					function ( $gateway_id ) {
						return false;
					}
				),
			)
		);

		$sut = new Email( $api, $logger );

		$order         = new WC_Order();
		$sent_to_admin = false;
		$plain_text    = false;

		add_filter(
			'wc_get_template',
			function (): string {
				throw new \Exception();
			}
		);

		$e = null;
		try {
			$sut->print_instructions( $order, $sent_to_admin, $plain_text );
		} catch ( \Exception $exception ) {
			$e = $exception;
		}

		// Is there a better way to say wc_get_template was called?
		$this->assertNull( $e );
	}

	/**
	 * @covers ::print_instructions
	 */
	public function test_print_instructions_exception_in_api(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_bitcoin_gateway'          => Expected::once(
					function ( $gateway_id ) {
						return true;
					}
				),
				'get_formatted_order_details' => Expected::once(
					function ( $order ) {
						throw new \Exception( 'no address exception' );
					}
				),
			)
		);

		$sut = new Email( $api, $logger );

		$order = new WC_Order();
		$order->save();
		$sent_to_admin = false;
		$plain_text    = false;

		$sut->print_instructions( $order, $sent_to_admin, $plain_text );

		// Is there a better way to say wc_get_template was called?
		$this->assertTrue( $logger->hasWarningThatContains( 'no address exception' ) );
	}
}
