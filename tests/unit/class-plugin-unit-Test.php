<?php
/**
 * Tests for the root plugin file.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\WC_Logger\WC_PSR_Logger;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\Logger;

/**
 * Class Plugin_WP_Mock_Test
 */
class Plugin_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	public function tearDown(): void {
		\WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * Verifies the plugin initialization.
	 * Verifies the plugin does not output anything to screen.
	 */
	public function test_plugin_include(): void {

		if ( ! function_exists( '\Patchwork\redefine' ) ) {
			$this->markTestSkipped( 'Patchwork not loaded' );
		}

		// Prevents code-coverage counting, and removes the need to define the WordPress functions that are used in that class.
		\Patchwork\redefine(
			array( BH_WP_Bitcoin_Gateway::class, 'register_hooks' ),
			function () {}
		);
		\Patchwork\redefine(
			array( Logger::class, '__construct' ),
			function () {}
		);

		global $plugin_root_dir;

		\WP_Mock::userFunction(
			'plugin_dir_path',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => $plugin_root_dir . '/',
			)
		);

		// Defined in `bootstrap.php`.
		global $plugin_basename;
		\WP_Mock::userFunction(
			'plugin_basename',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => $plugin_basename,
			)
		);

		\WP_Mock::userFunction(
			'plugins_url',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => 'http://localhost:8080/bh-wp-bitcoin-gateway/',
			)
		);

		\WP_Mock::userFunction(
			'trailingslashit',
			array(
				'args'    => array( \WP_Mock\Functions::type( 'string' ) ),
				'ret_arg' => true,
			)
		);

		\WP_Mock::userFunction(
			'register_activation_hook'
		);

		\WP_Mock::userFunction(
			'register_deactivation_hook'
		);

		/**
		 * Just for {@see WC_PSR_Logger}. Once we go back to BH_WP_Logger we can remove this.
		 */
		\WP_Mock::userFunction(
			'did_action',
			array(
				'return' => false,
			)
		);

		ob_start();

		include $plugin_root_dir . '/bh-wp-bitcoin-gateway.php';

		$printed_output = ob_get_contents();

		ob_end_clean();

		$this->assertEmpty( $printed_output );

		$this->assertArrayHasKey( 'bh_wp_bitcoin_gateway', $GLOBALS );

		$this->assertInstanceOf( API_Interface::class, $GLOBALS['bh_wp_bitcoin_gateway'] );
	}
}
