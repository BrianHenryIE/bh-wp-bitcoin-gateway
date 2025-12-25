<?php
/**
 * Add additional settings (`woocommerce_checkout_page_id`) to  `/wp-json/wp/v2/settings`.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest;

/**
 * A bit hacky – modify `global $wp_registered_settings` before it is used.
 */
class WooCommerce_Settings {

	/**
	 * Add hooks to register the REST endpoints.
	 */
	public function register_hooks(): void {
		add_filter( 'rest_pre_dispatch', array( $this, 'show_settings_in_rest' ) );
	}

	/**
	 * Expose settings through the REST API.
	 *
	 * `woocommerce_checkout_page_id`
	 *
	 * @hooked rest_pre_dispatch
	 *
	 * @param null|mixed $short_circuit The value to return.
	 *
	 * @see get_registered_settings
	 * /wp-json/wp/v2/settings
	 *
	 * @see WP_REST_Settings_Controller
	 */
	public function show_settings_in_rest( mixed $short_circuit ): mixed {
		global $wp_registered_settings;

		if ( ! in_array( 'woocommerce_checkout_page_id', $wp_registered_settings, true ) ) {
			$wp_registered_settings['woocommerce_checkout_page_id'] = array(
				'show_in_rest'      => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			);
		}

		return $short_circuit;
	}
}
