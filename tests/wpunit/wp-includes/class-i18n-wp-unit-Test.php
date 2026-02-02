<?php
/**
 * Tests for I18n. Tests load_plugin_textdomain.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use lucatume\WPBrowser\TestCase\WPTestCase;
use WP_Textdomain_Registry;

/**
 * Class I18n_Test
 *
 * @see I18n
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\I18n
 */
class I18n_WP_Unit_Test extends WPTestCase {

	/**
	 * Checks if the filter run by WordPress in the load_plugin_textdomain() function is called.
	 *
	 * @see load_plugin_textdomain()
	 */
	public function test_load_plugin_textdomain_function(): void {

		/** @var WP_Textdomain_Registry $wp_textdomain_registry */
		global $wp_textdomain_registry;

		/** @var false $before */
		$before = $wp_textdomain_registry->get( 'bh-wp-bitcoin-gateway', 'en-IE' );

		$i18n = new I18n();

		$i18n->load_plugin_textdomain();

		$after = $wp_textdomain_registry->get( 'bh-wp-bitcoin-gateway', 'en-IE' );

		$this->assertNotSame( $before, $after );

		$this->assertEquals( 'wp-content/plugins' . codecept_root_dir( 'languages/' ), $after );
	}
}
