<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\ColorLogger\ColorLogger;
use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Test\Unit;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Frontend_Assets
 */
class Frontend_Assets_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();

		/** @phpstan-ignore argument.type */
		WP_Mock::passthruFunction( 'absint', array( 'return' => fn( $value ) => intval( $value ) ) );
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * Verifies enqueue_styles() calls wp_enqueue_style() with appropriate parameters.
	 * Verifies the .css file exists.
	 *
	 * @covers ::enqueue_styles
	 * @covers ::__construct
	 * @see wp_enqueue_style()
	 */
	public function test_enqueue_styles(): void {

		/** @var string $plugin_root_dir */
		global $plugin_root_dir;
		$plugin_root_url = 'http://localhost:8080/bh-wp-bitcoin-gateway/wp-content/plugins/bh-wp-bitcoin-gateway/';

		$logger   = new ColorLogger();
		$api      = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once(
					fn( int $order_id ) => true
				),
			)
		);
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_version' => Expected::once(
					fn() => '1.0.0'
				),
				'get_plugin_url'     => $plugin_root_url,
			)
		);

		$css_file = $plugin_root_dir . '/assets/css/bh-wp-bitcoin-gateway.css';
		$css_url  = $plugin_root_url . 'assets/css/bh-wp-bitcoin-gateway.css';

		\WP_Mock::userFunction(
			'wp_enqueue_style',
			array(
				'times' => 1,
				'args'  => array( 'bh-wp-bitcoin-gateway', $css_url, array(), '1.0.0', 'all' ),
			)
		);

		\WP_Mock::userFunction(
			'wp_enqueue_style',
			array(
				'times' => 1,
				'args'  => array( 'dashicons' ),
			)
		);

		$GLOBALS['order-received'] = 123;

		$sut = new Frontend_Assets( $api, $settings, $logger );

		$sut->enqueue_styles();

		$this->assertFileExists( $css_file );
	}

	/**
	 * @covers ::enqueue_styles
	 */
	public function test_enqueue_styles_not_on_other_pages(): void {

		$logger   = new ColorLogger();
		$api      = $this->makeEmpty( API_WooCommerce_Interface::class );
		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_version' => Expected::never(),
			)
		);

		// Return any old url.
		\WP_Mock::userFunction(
			'plugin_dir_url',
			array(
				'times' => 0,
			)
		);

		\WP_Mock::userFunction(
			'wp_enqueue_style',
			array(
				'times' => 0,
			)
		);

		$sut = new Frontend_Assets( $api, $settings, $logger );

		$sut->enqueue_styles();
	}
}
