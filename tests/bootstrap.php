<?php
/**
 * @package           brianhenryie/bh-wp-bitcoin-gateway
 */

$GLOBALS['project_root_dir']   = $project_root_dir  = dirname( __DIR__, 1 );
$GLOBALS['plugin_root_dir']    = $plugin_root_dir   = $project_root_dir;
$GLOBALS['plugin_slug']        = $plugin_slug       = basename( $project_root_dir );
$GLOBALS['plugin_file_php']    = $plugin_file_php   = $plugin_slug . '.php';
$GLOBALS['plugin_path_php']    = $plugin_root_dir . '/' . $plugin_file_php;
$GLOBALS['plugin_basename']    = $plugin_slug . '/' . $plugin_file_php;
$GLOBALS['wordpress_root_dir'] = $project_root_dir . '/wordpress';

// If there is a secrets file, load it here.
// Unsure how to define it in codeception.yml while also not committing to GitHub.
$env_secret_fullpath = realpath( __DIR__ . '/../.env.secret' );
if ( file_exists( $env_secret_fullpath ) ) {

	$dotenv = Dotenv\Dotenv::createImmutable( codecept_root_dir(), '.env.secret' );
	$dotenv->load();
}


$is_integration_test = array_reduce(
	$_SERVER['argv'],
	fn( $carry, $arg ) => $carry || 'integration' === $arg,
	false
);
if ( $is_integration_test ) {
	global $arbitrary_plugins;
	$arbitrary_plugins = array(
		dirname( __DIR__, 1 ) . '/bh-wp-bitcoin-gateway.php',
	);
}
