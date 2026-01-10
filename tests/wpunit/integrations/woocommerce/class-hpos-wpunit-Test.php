<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\HPOS
 */
class HPOS_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::declare_compatibility
	 * @covers ::__construct
	 */
	public function test_declare_compatibility(): void {

		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => Expected::once( 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php' ),
			)
		);

		/**
		 * `doing_action('before_woocommerce_init')` must be true.
		 *
		 * @var string[] $wp_current_filter
		 */
		global $wp_current_filter;
		$wp_current_filter[] = 'before_woocommerce_init';

		wp_cache_init();

		// because the plugin is not in the wp-content/plugins/ directory, we need to mock the plugins cache.
		$cache_plugins = array(
			'' => array(
				'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php' =>
					array(
						'Name' => 'Bitcoin Gateway',
					),
			),
		);
		wp_cache_set( 'plugins', $cache_plugins, 'plugins', 10 );

		$sut = new HPOS( $settings );

		$sut->declare_compatibility();

		/** @var array{compatible:array<string>, incompatible:array<string>} $result */
		$result = FeaturesUtil::get_compatible_plugins_for_feature( 'custom_order_tables' );

		$this->assertContains( 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php', $result['compatible'], wp_json_encode( $result['compatible'] ) ?: '' );
	}
}
