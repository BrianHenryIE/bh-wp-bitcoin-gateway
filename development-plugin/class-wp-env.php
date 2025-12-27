<?php
/**
 * Fix for cron jobs not working in wp-env.
 *
 * @see https://github.com/WordPress/gutenberg/issues/20569
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin;

use Exception;

/**
 * Modify the URL used in requests to itself.
 */
class WP_Env {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->record_hostname();
	}

	/**
	 * Partly pulled from wp-graphql, where they generate a mu-plugin during wp-env boot script.
	 */
	public function register_hooks(): void {
		add_filter( 'site_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
		add_filter( 'home_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
		add_filter( 'wp_login_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
		add_filter( 'admin_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
	}

	/**
	 * Record the container's hostname that WordPress sees for itself.
	 *
	 * Do not record it when running in the `cli` or `tests-cli` containers.
	 */
	protected function record_hostname(): void {

		if ( defined( 'WP_CLI' ) && ( true === constant( 'WP_CLI' ) ) ) {
			return;
		}

		$hostname = gethostname();

		if ( ! $hostname ) {
			return;
		}

		update_option( 'wp_env_cron_hostname', $hostname );
	}

	/**
	 * Replace the URL when it is an internal cron request or a(n internal) WP CLI request.
	 *
	 * @see get_site_url()
	 * @see cron.php:957
	 *
	 * @param string $url   The full URL.
	 * @param string $_path The URL path.
	 *
	 * @throws Exception If an error occurs running `preg_replace()` on the URL.
	 */
	public function wpenv_fix_url( string $url, string $_path = '' ): string {

		// TODO: Check are we about to replace the URL first before doing this work.
		$internal_url = $this->get_internal_url( $url );

		if ( ( isset( $_SERVER['REQUEST_URI'] ) && 'wp-cron.php' === $_SERVER['REQUEST_URI'] ) || wp_doing_cron() ) {
			$url_for_cron_hostname = 'http://' . get_option( 'wp_env_cron_hostname' ) . '/wp-cron.php';
			return $internal_url;
		}

		if ( defined( 'WP_CLI' ) && ( true === constant( 'WP_CLI' ) ) ) {
			$url_for_cron_hostname = 'http://' . get_option( 'wp_env_cron_hostname' );
			return $internal_url;
		}

		return $url;
	}

	/**
	 * Given a `localhost` or `127.0.0.1` URL, strip the port.
	 *
	 * @param string $url Whatever URL is about to be used.
	 *
	 * @throws Exception If the regex were to (unlikely) fail.
	 */
	protected function get_internal_url( string $url ): string {
		return preg_replace( '#(https?://(localhost|127.0.0.1)):\d{1,6}#', '$1', $url )
			?? ( fn() => throw new Exception( 'The `WP_Env::get_internal_url()` regex failed.' ) )();
	}
}
