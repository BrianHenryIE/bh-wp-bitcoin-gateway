<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use Exception;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\AJAX
 */
class AJAX_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::get_order_details
	 * @covers ::__construct
	 */
	public function test_bad_nonce(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty( API_WooCommerce_Interface::class );

		$sut = new AJAX( $api, $logger );

		\WP_Mock::userFunction(
			'check_ajax_referer',
			array(
				'return' => false,
				'times'  => 1,
			)
		);

		\WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'array' ), 400 ),
				'times'  => 1,
				'return' => function () {
					throw new Exception();
				},
			)
		);

		self::expectException( \Exception::class );

		$sut->get_order_details();
	}

	/**
	 * @covers ::get_order_details
	 */
	public function test_get_order_details_no_order_id(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty( API_WooCommerce_Interface::class );

		unset( $_POST['order_id'] );

		$sut = new AJAX( $api, $logger );

		\WP_Mock::userFunction(
			'check_ajax_referer',
			array(
				'return' => true,
				'times'  => 1,
			)
		);

		\WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'array' ), 400 ),
				'times'  => 1,
				'return' => function () {
					throw new Exception();
				},
			)
		);

		self::expectException( \Exception::class );

		$sut->get_order_details();
	}

	/**
	 * @covers ::get_order_details
	 */
	public function test_get_order_details_no_order_object(): void {

		$logger = new ColorLogger();
		$api    = $this->makeEmpty( API_WooCommerce_Interface::class );

		$_POST['order_id'] = 123;

		$sut = new AJAX( $api, $logger );

		\WP_Mock::userFunction(
			'check_ajax_referer',
			array(
				'return' => true,
				'times'  => 1,
			)
		);

		\WP_Mock::passthruFunction( 'wp_unslash' );

		\WP_Mock::userFunction(
			'wc_get_order',
			array(
				'args'   => 123,
				'times'  => 1,
				'return' => false,
			)
		);

		\WP_Mock::userFunction(
			'wp_send_json_error',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'array' ), 400 ),
				'times'  => 1,
				'return' => function () {
					throw new Exception();
				},
			)
		);

		self::expectException( \Exception::class );

		$sut->get_order_details();
	}
}
