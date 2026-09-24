<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use WC_Order;
use WP_Post;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Admin_Order_UI
 */
class Admin_Order_UI_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::print_address_transactions_metabox
	 * @covers ::__construct
	 */
	public function test_print_address_transactions_metabox(): void {

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

		$sut = new Admin_Order_UI( $api, $logger );

		/** @var WP_Post $post */
		$post = get_post( $order_id );

		// Record that the template was requested, then abort rendering it (the exception is caught and logged by the sut).
		$template_requested = false;
		add_filter(
			'wc_get_template',
			function () use ( &$template_requested ): string {
				$template_requested = true;
				throw new \Exception( 'template requested' );
			}
		);

		$sut->print_address_transactions_metabox( $post );

		$this->assertTrue( $template_requested );
	}

	/**
	 * @covers ::print_address_transactions_metabox
	 */
	public function test_print_address_transactions_metabox_not_bitcoin_gateway(): void {

		$logger = new ColorLogger();

		$order    = new WC_Order();
		$order_id = $order->save();

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order' => function () use ( $order_id ) {
					return null;
				},
			)
		);

		$sut = new Admin_Order_UI( $api, $logger );

		/** @var WP_Post $post */
		$post = get_post( $order_id );

		add_filter(
			'wc_get_template',
			function (): string {
				throw new \Exception();
			}
		);

		$e = null;
		try {
			$sut->print_address_transactions_metabox( $post );
		} catch ( \Exception $exception ) {
			$e = $exception;
		}

		// Is there a better way to say wc_get_template was called?
		$this->assertNull( $e );
	}

	/**
	 * @covers ::print_address_transactions_metabox
	 */
	public function test_print_address_transactions_metabox_exception_in_api(): void {

		$logger = new ColorLogger();

		$order    = new WC_Order();
		$order_id = $order->save();

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order'           => function () use ( $order_id ) {
					return $this->make(
						WC_Bitcoin_Order::class,
						array(
							'get_id' => $order_id,
						)
					);
				},
				'get_formatted_order_details' => Expected::once(
					function ( $order ): array {
						throw new \Exception( 'no btc address exception' );
					}
				),
			)
		);

		$sut = new Admin_Order_UI( $api, $logger );

		/** @var WP_Post $post */
		$post = get_post( $order_id );

		$sut->print_address_transactions_metabox( $post );

		// Is there a better way to say wc_get_template was called?
		$this->assertTrue( $logger->hasWarningThatContains( 'no btc address exception' ) );
	}

	/**
	 * An `Error` while loading the order (previously outside the try/catch, which only caught `Exception`) was a
	 * fatal on the order edit screen.
	 *
	 * @covers ::print_address_transactions_metabox
	 */
	public function test_print_address_transactions_metabox_logs_and_does_not_throw_on_error(): void {

		$logger = new ColorLogger();

		$order    = new WC_Order();
		$order_id = $order->save();

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

		$sut = new Admin_Order_UI( $api, $logger );

		/** @var WP_Post $post */
		$post = get_post( $order_id );

		$sut->print_address_transactions_metabox( $post );

		$this->assertTrue( $logger->hasWarningThatContains( 'getTimestamp() on null' ) );
	}

	/**
	 * A failed payment check (e.g. blockchain API rate limit) should still show the last known details.
	 *
	 * @covers ::print_address_transactions_metabox
	 */
	public function test_print_address_transactions_metabox_shows_details_when_payment_check_fails(): void {

		$logger = new ColorLogger();

		$order    = new WC_Order();
		$order_id = $order->save();

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order'           => function () use ( $order_id ) {
					return $this->make(
						WC_Bitcoin_Order::class,
						array(
							'get_id'  => $order_id,
							'is_paid' => false,
						)
					);
				},
				'check_order_for_payment'     => Expected::once(
					function () {
						throw new \RuntimeException( 'HTTP 429 Too Many Requests' );
					}
				),
				'get_formatted_order_details' => Expected::once( fn() => array() ),
			)
		);

		$sut = new Admin_Order_UI( $api, $logger );

		$template_requested = false;
		add_filter(
			'wc_get_template',
			function () use ( &$template_requested ): string {
				$template_requested = true;
				throw new \Exception( 'template requested' );
			}
		);

		/** @var WP_Post $post */
		$post = get_post( $order_id );

		$sut->print_address_transactions_metabox( $post );

		$this->assertTrue( $template_requested );
		$this->assertTrue( $logger->hasWarningThatContains( 'HTTP 429' ) );
	}
}
