<?php
/**
 * Plugin Name:       Bitcoin Gateway Development Plugin
 * Description:       Convenience, demo and test helper functions.
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wp-bitcoin-gateway/
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin;

use BrianHenryIE\WP_Bitcoin_Gateway\Alley_Interactive\Autoloader\Autoloader;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Admin\WooCommerce;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Admin\WooCommerce_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest\Action_Scheduler;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest\Bitcoin;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest\Themes;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest\WooCommerce_Settings;

/**
 * TODO check for stray requests: exchange rate query seems to be happening too frequently.
 * https://api-pub.bitfinex.com/v2/tickers?symbols=tBTCUSD
 */

if ( ! defined( 'WPINC' ) ) {
	return;
}

if ( ! is_plugin_active( 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php' ) ) {
	return;
}

Autoloader::generate(
	'BrianHenryIE\\WP_Bitcoin_Gateway\\Development_Plugin',
	__DIR__,
)->register();

// `wp-env` fixes.
new WP_Env()->register_hooks();

// Authentication helpers.
new Authentication()->register_hooks();

// Admin UI changes.
new WooCommerce()->register_hooks();
new WooCommerce_Order()->register_hooks();

// New REST endpoints.
new Action_Scheduler()->register_hooks();
new Bitcoin()->register_hooks();
new Themes()->register_hooks();
new WooCommerce_Settings()->register_hooks();
