<?php
/**
 *
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

/**
 * Class Plugin_WP_Mock_Test
 *
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\I18n
 */
class I18n_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	#[\Override]
	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * Verify load_plugin_textdomain is correctly called.
	 *
	 * @covers ::load_plugin_textdomain
	 */
	public function test_load_plugin_textdomain(): void {

		\WP_Mock::userFunction(
			'plugin_basename',
			array(
				'args'   => array(
					\WP_Mock\Functions::type( 'string' ),
				),
				'return' => 'bh-wp-bitcoin-gateway',
				'times'  => 1,
			)
		);

		\WP_Mock::userFunction(
			'load_plugin_textdomain',
			array(
				'times' => 1,
				'args'  => array(
					'bh-wp-bitcoin-gateway',
					false,
					'bh-wp-bitcoin-gateway/languages/',
				),
			)
		);

		$i18n = new I18n();
		$i18n->load_plugin_textdomain();
	}
}
