<?php
/**
 * Plugin Name:  Try to load a plugin that is not inside the plugins' directory.
 *
 * MU plugins are loaded after `global $wp_plugin_paths` is set but before normal plugins are loaded.
 */

function activate_plugin_at_arbitrary_path( string $plugin_file_path, ?string $plugin_basename = null ) {
	if(!file_exists($plugin_file_path)) {
		return;
	}

	wp_register_plugin_realpath( $plugin_file_path );

	$plugin_basename = $plugin_basename ?? basename(dirname($plugin_file_path)) . '/' . basename( $plugin_file_path );

	add_filter("option_active_plugins", function($plugins) use ($plugin_basename ){
		$plugins[] = 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php';
		return $plugins;
	});

	// Actually load the plugin.
	include_once $plugin_file_path;
}

global $arbitrary_plugins;

foreach((array) $arbitrary_plugins as $plugin_file_path) {
	activate_plugin_at_arbitrary_path($plugin_file_path);
}


