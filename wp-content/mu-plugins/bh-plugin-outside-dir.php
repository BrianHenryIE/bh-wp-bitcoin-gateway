<?php
/**
 * Plugin Name:  Try to load a plugin that is not inside the plugins' directory.
 *
 * MU plugins are loaded after `global $wp_plugin_paths` is set but before normal plugins are loaded.
 *
 * I'm currently setting `$arbitrary_plugins` in `/tests/bootstrap.php`.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

/**
 * `include`s the plugin file, and adds a filter so `option_active_plugins` shows it is active.
 *
 * @param string      $plugin_file_path Absolute path to main plugin file.
 * @param string|null $plugin_basename `dir/file.php` for plugin from WP_PLUGIN_DIR.
 */
function activate_plugin_at_arbitrary_path( string $plugin_file_path, ?string $plugin_basename = null ): void {
	if ( ! file_exists( $plugin_file_path ) ) {
		return;
	}

	wp_register_plugin_realpath( $plugin_file_path );

	$plugin_basename = $plugin_basename ?? basename( dirname( $plugin_file_path ) ) . '/' . basename( $plugin_file_path );

	/**
	 * @see get_option()
	 */
	add_filter(
		'option_active_plugins',
		function ( $plugins ) use ( $plugin_basename ): array {
			$plugins   = array_filter( (array) $plugins );
			$plugins[] = $plugin_basename;
			return $plugins;
		}
	);

	// Actually load the plugin.
	include_once $plugin_file_path;
}

/** @var null|array<string> $arbitrary_plugins Array of absolute paths to plugins' main files, if defined. */
global $arbitrary_plugins;

foreach ( (array) $arbitrary_plugins as $plugin_file_path ) {
	activate_plugin_at_arbitrary_path( $plugin_file_path );
}
