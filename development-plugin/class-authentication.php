<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin;

use Exception;
use WP_Error;
use WP_REST_Server;
use WP_User;

class Authentication {

	public function register_hooks(): void {
		/**
		 * @see \Automattic\WooCommerce\StoreApi\Routes\V1\AbstractCartRoute::check_nonce()
		 */
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		add_filter( 'rest_authentication_errors', array( $this, 'set_rest_user_admin' ) );

		add_action( 'init', array( $this, 'login_as_any_user' ) );
	}

	/**
	 * @param WP_Error|null|true $errors WP_Error if authentication error, null if authentication method wasn't used, true if authentication succeeded.
	 *
	 * @see WP_REST_Server::check_authentication()
	 * @hooked rest_authentication_errors
	 */
	public function set_rest_user_admin( $errors ): mixed {

		/**
		 * Don't affect logged out behaviour for the store.
		 * /wp-json/wc/store/v1/batch?_locale=site
		 * /wp-json/wc/store/
		 */
		if ( str_starts_with( $_SERVER['REQUEST_URI'], '/wp-json/wc/store' ) ) {
			return $errors;
		}

		wp_set_current_user( 1 );

		return $errors;
	}

	/**
	 * @hooked init
	 * @throws Exception
	 */
	public function login_as_any_user(): void {
		if ( isset( $_GET['login_as_user'] ) ) {
			$login_as_user = sanitize_text_field( $_GET['login_as_user'] );
			/** @var WP_User|false $wp_user */
			$wp_user = get_user_by( 'slug', $login_as_user );
			if ( ! $wp_user ) {
				throw new Exception( 'Could not find user: ' . $login_as_user );
			}
			wp_set_current_user( $wp_user->ID );
			wp_set_current_user( $wp_user->ID, $wp_user->user_login );
			wp_set_auth_cookie( $wp_user->ID );
		}
	}
}
