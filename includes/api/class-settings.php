<?php
/**
 * Object containing the plugin settings.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Plugins_Page;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Frontend_Assets;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\Logger_Settings_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\Logger_Settings_Trait;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\WooCommerce_Logger_Settings_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Psr\Log\LogLevel;

/**
 * Plain object pulling setting from wp_options.
 */
class Settings implements Settings_Interface, WooCommerce_Logger_Settings_Interface {
	use Logger_Settings_Trait;

	/**
	 * The minimum severity of logs to record.
	 *
	 * @see LogLevel
	 */
	public function get_log_level(): string {
		$default_log_level = LogLevel::NOTICE;
		/**
		 * TODO: sync plugin log level with gateway option.
		 *
		 * @see Bitcoin_Gateway::process_admin_options()
		 */
		$saved_setting = get_option( 'bh_wp_bitcoin_gateway_log_level', $default_log_level );
		$log_levels    = array( LogLevel::DEBUG, LogLevel::INFO, LogLevel::ERROR, LogLevel::NOTICE, LogLevel::WARNING, 'none' );
		return in_array( $saved_setting, $log_levels, true ) ? $saved_setting : $default_log_level;
	}

	/**
	 * Plugin name for use by the logger in friendly messages printed to WordPress admin UI.
	 *
	 * @see Logger
	 */
	public function get_plugin_name(): string {
		return 'Bitcoin Gateway';
	}

	/**
	 * The plugin slug is used by the logger in file and URL paths.
	 */
	public function get_plugin_slug(): string {
		return 'bh-wp-bitcoin-gateway';
	}

	/**
	 * Used to add links on plugins.php.
	 *
	 * @used-by Plugins_Page
	 *
	 * @see Logger_Settings_Interface::get_plugin_basename()
	 */
	public function get_plugin_basename(): string {
		if (
			defined( 'BH_WP_BITCOIN_GATEWAY_BASENAME' )
			&& is_string( constant( 'BH_WP_BITCOIN_GATEWAY_BASENAME' ) )
		) {
			/** @var string $plugin_file_basename */
			$plugin_file_basename = constant( 'BH_WP_BITCOIN_GATEWAY_BASENAME' );
			return $plugin_file_basename;
		}

		return 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php';
	}

	/**
	 * The plugin version, as used in caching JS and CSS assets.
	 */
	public function get_plugin_version(): string {
		if (
			defined( 'BH_WP_BITCOIN_GATEWAY_VERSION' )
			&& is_string( constant( 'BH_WP_BITCOIN_GATEWAY_VERSION' ) ) // @phpstan-ignore booleanAnd.rightAlwaysTrue
			&& $this->is_valid_version_string( constant( 'BH_WP_BITCOIN_GATEWAY_VERSION' ) )
		) {
			return constant( 'BH_WP_BITCOIN_GATEWAY_VERSION' );
		}
		return '2.0.0';
	}

	/**
	 * Confirm the string is a ~semver version.
	 *
	 * @param string $version_string Assumed to be a version e.g. "1.2.3".
	 */
	protected function is_valid_version_string( string $version_string ): bool {
		return (bool) preg_match( '/^[0-9]+(\.[0-9]+)*([a-zA-Z0-9\-]+)?$/', $version_string );
	}

	/**
	 * Return the URL of the base of the plugin.
	 *
	 * @used-by Frontend_Assets::enqueue_scripts()
	 * @used-by Frontend_Assets::enqueue_styles()
	 */
	public function get_plugin_url(): string {
		if (
			defined( 'BH_WP_BITCOIN_GATEWAY_URL' )
			&& is_string( constant( 'BH_WP_BITCOIN_GATEWAY_URL' ) )
		) {
			/** @var string $plugin_url */
			$plugin_url = constant( 'BH_WP_BITCOIN_GATEWAY_URL' );
			return $plugin_url;
		}
		return plugins_url( $this->get_plugin_basename() );
	}

	/**
	 * Get the absolute path to the plugin root on the server filesystem, with trailingslash.
	 */
	public function get_plugin_dir(): string {
		if (
			defined( 'BH_WP_BITCOIN_GATEWAY_PATH' )
			&& is_string( constant( 'BH_WP_BITCOIN_GATEWAY_PATH' ) )
		) {
			/** @var string $plugin_dir */
			$plugin_dir = constant( 'BH_WP_BITCOIN_GATEWAY_PATH' );
			return $plugin_dir;
		}
		/**
		 * @see wp_plugin_directory_constants()
		 * @var string $wp_plugin_dir
		 */
		$wp_plugin_dir = constant( 'WP_PLUGIN_DIR' );
		return $wp_plugin_dir . '/' . plugin_dir_path( $this->get_plugin_basename() );
	}
}
