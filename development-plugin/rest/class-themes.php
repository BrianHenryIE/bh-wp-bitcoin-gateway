<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest;

use JsonException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Themes {
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'bh_activate_theme' ) );
		add_action( 'rest_api_init', array( $this, 'register_get_theme_list_route' ) );
		add_action( 'rest_api_init', array( $this, 'register_test_helper_rest_active_theme_route' ) );
	}


	/**
	 * @throws JsonException
	 */
	public function activate_custom_theme_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		$request_body = json_decode( $request->get_body(), true, 512, JSON_THROW_ON_ERROR );
		// $request_body = $request->get_params();

		$theme_slug = ( (string) $request_body['theme_slug'] ) ?: null;

		if ( ! $theme_slug ) {
			return new WP_Error( 'rest_missing_param', 'Missing theme_slug parameter: ' . $request->get_body(), array( 'status' => 400 ) );
		}

		// Check if the theme exists.
		if ( ! wp_get_theme( $theme_slug )->exists() ) {
			return new WP_Error( 'rest_theme_not_found', 'Theme not found.', array( 'status' => 404 ) );
		}

		// Activate the theme.
		switch_theme( $theme_slug );

		return new WP_REST_Response(
			array(
				'message'    => 'Theme activated successfully.',
				'theme_slug' => $theme_slug,
			),
			200
		);
	}

	/**
	 * Register `e2e-test-helper/v1/activate` route.
	 *
	 * @hooked rest_api_init
	 */
	public function bh_activate_theme(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'activate_custom_theme_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Register `e2e-test-helper/v1/get-theme-list` route.
	 *
	 * @hooked rest_api_init
	 */
	public function register_get_theme_list_route(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/get-theme-list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'theme_list_function' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get a list of themes
	 *
	 * @return string[] The theme slugs.
	 */
	public function theme_list_function(): array {
		$list = wp_get_themes();

		return array_keys( $list );
	}

	/**
	 * Path to rest endpoint.
	 *
	 * @hooked rest_api_init
	 */
	public function register_test_helper_rest_active_theme_route(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/active_theme',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'active_theme' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Get the theme.
	 *
	 * @return array{slug: string} The currently active theme.
	 */
	public function active_theme(): array {
		return array( 'slug' => get_template() );
	}
}
