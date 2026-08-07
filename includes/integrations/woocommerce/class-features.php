<?php
/**
 * Declare compatibility with WooCommere's new High Performance Order Storage (database tables).
 *
 * Rather, assert we are not doing anything incompatible!
 *
 * @see https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;

/**
 * Message FeaturesUtil that this plugin has no incompatibilities with HPOS.
 *
 * @see https://woocommerce.com/document/high-performance-order-storage/
 */
class Features {

	/**
	 * Constructor
	 *
	 * @param Settings_Interface $settings The plugin's settings.
	 */
	public function __construct(
		protected Settings_Interface $settings // For the plugin basename.
	) {
	}

	/**
	 * Register compatibility with HPOS.
	 *
	 * We do not use any funky SQL for orders, just WooCommerce's CRUD function.
	 *
	 * @hooked before_woocommerce_init
	 * @see WooCommerce::init()
	 */
	public function declare_custom_order_tables_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			$this->settings->get_plugin_basename(),
			true
		);
	}

	/**
	 * Register compatibility with blocks checkout.
	 *
	 * @hooked before_woocommerce_init
	 * @see WooCommerce::init()
	 */
	public function declare_cart_checkout__blocks_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			$this->settings->get_plugin_basename(),
			true
		);
	}
}
