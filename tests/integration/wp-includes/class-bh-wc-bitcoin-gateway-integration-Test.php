<?php
/**
 * Tests for BH_WP_Bitcoin_Gateway main setup class. Tests the actions are correctly added.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\Frontend\Frontend_Assets;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\I18n;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * Class Develop_Test
 */
class BH_WP_Bitcoin_Gateway_Integration_Test extends WPTestCase {

	/**
	 * @return array<array{0:string, 1:class-string, 2:string}>
	 */
	public function hooks(): array {
		$hooks = array(
			array( 'init', I18n::class, 'load_plugin_textdomain' ),
			array( 'wp_enqueue_scripts', Frontend_Assets::class, 'enqueue_styles' ),
			array( 'wp_enqueue_scripts', Frontend_Assets::class, 'enqueue_scripts' ),
		);
		return $hooks;
	}

	/**
	 * @dataProvider hooks
	 *
	 * @param string $action_name The name of the action.
	 * @param string $class_type The expected class.
	 * @param string $method_name The class's method.
	 * @param int    $expected_priority The priority of the action (default: 10).
	 */
	public function test_is_function_hooked_on_action( string $action_name, string $class_type, string $method_name, int $expected_priority = 10 ): void {

		/** @var array<string,array<int,array<array{function:array{0:object,1:string}|callable|string}>>> $wp_filter */
		global $wp_filter;

		$this->assertArrayHasKey( $action_name, $wp_filter, "$method_name definitely not hooked to $action_name" );

		$actions_hooked = $wp_filter[ $action_name ];

		$this->assertArrayHasKey( $expected_priority, $actions_hooked, "$method_name definitely not hooked to $action_name priority $expected_priority" );

		$hooked_method = null;
		foreach ( $actions_hooked[ $expected_priority ] as $action ) {
			$action_function = $action['function'];
			if ( is_array( $action_function ) ) {
				if ( $action_function[0] instanceof $class_type ) {
					if ( $method_name === $action_function[1] ) {
						$hooked_method = $action_function[1];
						break;
					}
				}
			}
		}

		$this->assertNotNull( $hooked_method, "No methods on an instance of $class_type hooked to $action_name" );

		$this->assertEquals( $method_name, $hooked_method, "Unexpected method name for $class_type class hooked to $action_name" );
	}
}
