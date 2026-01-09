<?php
/**
 * PHPUnit bootstrap file for WP_Mock.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

/**
 * Requires XDebug 3.1 for debug/coverage modes, or returns null in XDebug 3.0.
 *
 * Earlier versions print out the settings to the screen.
 *
 * @see https://xdebug.org/docs/all_functions#xdebug_info
 *
 * @var string[] $xdebug_info
 */
if ( function_exists( 'xdebug_info' ) ) {
	$xdebug_info = xdebug_info( 'mode' ) ?? array();
} else {
	$xdebug_info = array();
}

/**
 * Do not use Patchwork if coverage is enabled.
 * There is an out of memory error occurring.
 *
 * `WP_Mock::setUsePatchwork( ! in_array( 'coverage', $xdebug_info, true ) );`
 *
 * @see https://patchwork2.org/
 */
WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();

/** @var string $project_root_dir Defined in {@see /tests/bootstrap.php} */
global $project_root_dir;

$class_map = array(
	WC_Settings_API::class    => $project_root_dir . '/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-settings-api.php',
	WC_Payment_Gateway::class => $project_root_dir . '/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php',
	WP_Screen::class          => $project_root_dir . '/wordpress/wp-admin/includes/class-wp-screen.php',
);
spl_autoload_register(
	function ( $classname ) use ( $class_map ) {

		if ( array_key_exists( $classname, $class_map ) && file_exists( $class_map[ $classname ] ) ) {
			require_once $class_map[ $classname ];
		}
	}
);

/** @var string $plugin_root_dir Defined in {@see /tests/bootstrap.php} */
global $plugin_root_dir;
require_once $plugin_root_dir . '/autoload.php';

define( 'HOUR_IN_SECONDS', 60 * 60 );
