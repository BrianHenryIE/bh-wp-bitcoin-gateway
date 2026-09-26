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
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Ajax\WooCommerce_Customer;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest\WooCommerce_Settings;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest\WooCommerce_Order as Rest_WooCommerce_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest\Private_Uploads;

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

/**
 * NEVER RUN THIS PLUGIN ON A REACHABLE SITE.
 *
 * It authenticates every non-Store REST request as the administrator, lets anyone log in as any user with
 * `?login_as_user=`, and registers open endpoints that delete wallets, seed payment addresses without a blockchain
 * check, and rewrite order dates. It exists only for the wp-env development site and CI. It is excluded from the
 * distributed build by .distignore, and this guard refuses to load anywhere that is not a local environment with
 * WP_DEBUG on.
 */
if ( 'local' !== wp_get_environment_type() || ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
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
new WooCommerce_Customer()->register_hooks();
new WooCommerce_Settings()->register_hooks();
new Rest_WooCommerce_Order()->register_hooks();
new Private_Uploads()->register_hooks();
