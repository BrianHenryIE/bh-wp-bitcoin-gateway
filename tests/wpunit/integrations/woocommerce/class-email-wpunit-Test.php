<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use Codeception\Stub\Expected;
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

		$order    = new WC_Order();
		$order_id = $order->save();

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order' => function () use ( $order_id ) {
					return $this->make(
						WC_Bitcoin_Order::class,
						array(
							'get_id' => $order_id,
						)
					);
				},
			)
		);

		$sut = new Email( $api, $logger );

		$sent_to_admin = false;
		$plain_text    = false;

		// Record that the template was requested, then abort rendering it (the exception is caught and logged by the sut).
		$template_requested = false;
		add_filter(
			'wc_get_template',
			function () use ( &$template_requested ): string {
				$template_requested = true;
				throw new \Exception( 'template requested' );
			}
		);

		$sut->print_instructions( $order, $sent_to_admin, $plain_text );

		$this->assertTrue( $template_requested );
	}


	/**
	 * @covers ::print_instructions
	 * @covers ::__construct
	 */
	public function test_print_instructions_admin_return_early(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
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
				'get_bitcoin_order' => Expected::once( fn() => null ),
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

		$order = new WC_Order();
		$order->save();

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order'           => function () {
					return $this->make( WC_Bitcoin_Order::class );
				},
				'get_formatted_order_details' => Expected::once(
					function ( $order ) {
						throw new \Exception( 'no address exception' );
					}
				),
			)
		);

		$sut = new Email( $api, $logger );

		$sent_to_admin = false;
		$plain_text    = false;

		$sut->print_instructions( $order, $sent_to_admin, $plain_text );

		// Is there a better way to say wc_get_template was called?
		$this->assertTrue( $logger->hasWarningThatContains( 'no address exception' ) );
	}

	/**
	 * The email hook runs inside `payment_complete()`; an `Error` while loading the order previously escaped and
	 * aborted payment processing.
	 *
	 * @covers ::print_instructions
	 */
	public function test_print_instructions_logs_and_does_not_throw_on_error(): void {

		$logger = new ColorLogger();

		$order = new WC_Order();
		$order->save();

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order' => Expected::once(
					function () {
						throw new \TypeError( 'Call to a member function getTimestamp() on null' );
					}
				),
			)
		);

		$sut = new Email( $api, $logger );

		$sut->print_instructions( $order, false, false );

		$this->assertTrue( $logger->hasWarningThatContains( 'getTimestamp() on null' ) );
	}
}
