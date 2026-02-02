<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use Codeception\Stub\Expected;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Plugins_Page
 */
class Plugins_Page_Unit_Test extends \Codeception\Test\Unit {

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
	 * @covers ::add_settings_action_link
	 */
	public function test_add_settings_action_link(): void {

		$settings = $this->makeEmpty( Settings_Interface::class );
		$sut      = new Plugins_Page( $settings );

		\WP_Mock::userFunction(
			'is_plugin_active',
			array(
				'times'  => 1,
				'args'   => array( 'woocommerce/woocommerce.php' ),
				'return' => true,
			)
		);

		\WP_Mock::userFunction(
			'admin_url',
			array(
				'times'      => 1,
				'return_arg' => true,
			)
		);

		\WP_Mock::userFunction(
			'__',
			array(
				'times'      => 1,
				'return_arg' => true,
			)
		);

		$result = $sut->add_settings_action_link( array() );

		$this->assertCount( 1, $result );

		$this->assertStringContainsString( 'Settings', $result[0] );
	}

	/**
	 * @covers ::add_settings_action_link
	 */
	public function test_add_settings_action_link_woocommerce_inactive(): void {

		$settings = $this->makeEmpty( Settings_Interface::class );
		$sut      = new Plugins_Page( $settings );

		\WP_Mock::userFunction(
			'is_plugin_active',
			array(
				'times'  => 1,
				'args'   => array( 'woocommerce/woocommerce.php' ),
				'return' => false,
			)
		);

		\WP_Mock::userFunction(
			'admin_url',
			array(
				'times'      => 0,
				'return_arg' => true,
			)
		);

		\WP_Mock::userFunction(
			'__',
			array(
				'times'      => 0,
				'return_arg' => true,
			)
		);

		$result = $sut->add_settings_action_link( array() );
	}

	/**
	 * @covers ::split_author_link_into_two_links
	 */
	public function test_split_author_link_into_two_links(): void {

		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => Expected::once( 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php' ),
			)
		);
		$sut      = new Plugins_Page( $settings );

		$plugin_meta     = array(
			0 => 'Version 1.3.3',
			1 => 'By <a href="https://github.com/Nullcorps/">Nullcorps, BrianHenryIE</a>',
			2 => '<a href="http://github.com/BrianHenryIE/bh-wp-bitcoin-gateway/" aria-label="Visit plugin site for BH WP Bitcoin Gateway">Visit plugin site</a>',
		);
		$plugin_filename = 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php';

		$result = $sut->split_author_link_into_two_links( $plugin_meta, $plugin_filename );

		$updated = 'By <a href="https://github.com/Nullcorps/">Nullcorps</a>, <a href="https://brianhenry.ie/">BrianHenryIE</a>';

		$this->assertEquals( $updated, $result[1] );
	}
}
