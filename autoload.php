<?php
/**
 * Loads all required classes
 *
 * Uses classmap, PSR4 & wp-namespace-autoloader.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           brianhenryie/bh-wp-bitcoin-gateway
 *
 * @see https://github.com/pablo-sg-pacheco/wp-namespace-autoloader/
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\Alley_Interactive\Autoloader\Autoloader;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Error is caught in root plugin file.
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
@require_once __DIR__ . '/vendor-prefixed/autoload.php';

Autoloader::generate(
	'BrianHenryIE\\WP_Bitcoin_Gateway',
	__DIR__ . '/includes',
)->register();
