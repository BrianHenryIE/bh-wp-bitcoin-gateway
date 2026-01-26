<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Templates
 */
class Templates_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::load_bitcoin_templates
	 * @covers ::__construct
	 */
	public function test_load_unrelated_template(): void {

		$settings = $this->makeEmpty( Settings_Interface::class );

		$sut = new Templates( $settings );

		$template      = '/path/to/woocommerce/templates/product-searchform.php';
		$template_name = 'product-searchform.php';

		$args          = array();
		$template_path = '';
		$default_path  = '';

		$result = $sut->load_bitcoin_templates( $template, $template_name, $args, $template_path, $default_path );

		$this->assertEquals( $template, $result );
	}


	/**
	 * @covers ::load_bitcoin_templates
	 */
	public function test_load_already_provided_template(): void {

		$settings = $this->makeEmpty( Settings_Interface::class );

		$sut = new Templates( $settings );

		// Use any existing file here for the test.
		$template      = __FILE__;
		$template_name = 'bitcoin-unpaid.php';
		$args          = array();
		$template_path = '';
		$default_path  = '';

		$result = $sut->load_bitcoin_templates( $template, $template_name, $args, $template_path, $default_path );

		$this->assertEquals( $template, $result );
	}


	/**
	 * @covers ::load_bitcoin_templates
	 */
	public function test_exists_in_theme_template(): void {

		$settings = $this->makeEmpty( Settings_Interface::class );

		$sut = new Templates( $settings );

		// Use any existing file here for the test.
		$template      = 'bitcoin-unpaid.php';
		$template_name = 'bitcoin-unpaid.php';
		$args          = array();
		$template_path = '';
		$default_path  = '';

		\WP_Mock::userFunction(
			'locate_template',
			array(
				'times'  => 1,
				'return' => 'expected-template-file-path',
			)
		);

		$result = $sut->load_bitcoin_templates( $template, $template_name, $args, $template_path, $default_path );

		$this->assertEquals( 'expected-template-file-path', $result );
	}

	/**
	 * @covers ::load_bitcoin_templates
	 */
	public function test_return_plugin_default_template(): void {

		$settings_mock = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_dir' => Expected::once( '/path/to/plugin/' ),
			)
		);

		$sut = new Templates( $settings_mock );

		// Use any existing file here for the test.
		$template      = 'bitcoin-unpaid.php';
		$template_name = 'bitcoin-unpaid.php';
		$args          = array();
		$template_path = '';
		$default_path  = '';

		\WP_Mock::userFunction(
			'locate_template',
			array(
				'times'  => 1,
				'return' => '', // WooCommerce's function returns an empty string, which I guess is falsey.
			)
		);

		$result = $sut->load_bitcoin_templates( $template, $template_name, $args, $template_path, $default_path );

		$this->assertEquals( '/path/to/plugin/templates/bitcoin-unpaid.php', $result );
	}
}
