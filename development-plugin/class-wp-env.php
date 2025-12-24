<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin;

class WP_Env {

	public function register_hooks(): void {

		// wp-env cron fix.

		// Do not run inside the WP-CLI container.
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			$hostname = gethostname();
			update_option( 'wp_env_cron_hostname', $hostname );
		}

		/**
		 * @see WP_Http::request()
		 */
		add_filter(
			'http_request_args',
			function ( $a ) {
				return $a;
			}
		);

		/**
		 * @see get_site_url()
		 * @see cron.php:957
		 */
		add_filter(
			'site_url',
			function ( $url, $path, ) {
				if ( 'wp-cron.php' === $path ) {
					return 'http://' . get_option( 'wp_env_cron_hostname' ) . '/wp-cron.php';
				}
				if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
					return 'http://' . get_option( 'wp_env_cron_hostname' );
				}
				return $url;
			},
			10,
			2
		);

		add_filter( 'site_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
		add_filter( 'home_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
		add_filter( 'wp_login_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
		add_filter( 'admin_url', array( $this, 'wpenv_fix_url' ), 1, 2 );
	}

	public function wpenv_fix_url( string $url, $path = '' ): string {

		$internal_url = preg_replace( '#(https?://(localhost|127.0.0.1)):888[8,9]#', '$1', $url );

		if ( 'wp-cron.php' === $_SERVER['REQUEST_URI'] || wp_doing_cron() ) {
			$url_for_cron_hostname = 'http://' . get_option( 'wp_env_cron_hostname' ) . '/wp-cron.php';
			return $internal_url;
		}

		if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
			$url_for_cron_hostname = 'http://' . get_option( 'wp_env_cron_hostname' );
			return $internal_url;
		}

		return $url;
	}
}
