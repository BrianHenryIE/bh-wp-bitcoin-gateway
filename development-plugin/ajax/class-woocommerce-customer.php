<?php
/**
 * AJAX endpoint to set WooCommerce customer billing and shipping data in the session.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Ajax;

use WC_Customer;

/**
 * Set customer data in WooCommerce session for testing.
 */
class WooCommerce_Customer {

	/**
	 * Add hooks to register the REST endpoint and AJAX handler.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_e2e_set_customer_data', $this->ajax_set_customer_data( ... ) );
		add_action( 'wp_ajax_nopriv_e2e_set_customer_data', $this->ajax_set_customer_data( ... ) );
		add_filter( 'woocommerce_get_script_data', $this->add_nonce_to_woocommerce_params( ... ) );
	}

	/**
	 * Add our custom nonce to woocommerce_params for easy access.
	 *
	 * @param false|array $params WooCommerce params.
	 * @return array{ajax_url:string, wc_ajax_url:string, i18n_view_cart:string, cart_url:string, is_cart:bool, cart_redirect_after_add:string}
	 */
	public function add_nonce_to_woocommerce_params( $params ): array {
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$params['e2e_set_customer_data_nonce'] = wp_create_nonce( 'e2e-set-customer-data' );
		return $params;
	}

	/**
	 * AJAX handler to set customer data (similar to WC_AJAX::update_order_review but for all fields).
	 */
	public function ajax_set_customer_data(): void {
		check_ajax_referer( 'e2e-set-customer-data', 'security' );

		if ( ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not installed' ) );
			return;
		}

		// Force WooCommerce initialization if not already done.
		if ( ! did_action( 'woocommerce_init' ) ) {
			WC();
		}

		// Initialize cart if needed (even if empty).
		if ( is_null( WC()->cart ) ) {
			WC()->initialize_cart();
		}

		// Initialize session if needed.
		if ( is_null( WC()->session ) || ! WC()->session->has_session() ) {
			WC()->initialize_session();
			// Force set a session cookie.
			WC()->session->set_customer_session_cookie( true );
		}

		/**
		 * Initialize customer if needed.
		 * (WooCommerce's class-woocommerce.php:122 has the incorrect return type-hint).
		 *
		 * @var ?WC_Customer $wc_customer
		 */
		$wc_customer = WC()->customer;
		if ( is_null( $wc_customer ) ) {
			WC()->customer = new WC_Customer( get_current_user_id(), true );
		}

		$billing_props  = array();
		$shipping_props = array();

		// Billing fields.
		$billing_fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' );
		foreach ( $billing_fields as $field ) {
			if ( isset( $_POST[ 'billing_' . $field ] ) && is_string( $_POST[ 'billing_' . $field ] ) ) {
				$billing_props[ 'billing_' . $field ] = sanitize_text_field( wp_unslash( $_POST[ 'billing_' . $field ] ) );
			}
		}

		// Shipping fields.
		$shipping_fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );
		foreach ( $shipping_fields as $field ) {
			if ( isset( $_POST[ 'shipping_' . $field ] ) && is_string( $_POST[ 'shipping_' . $field ] ) ) {
				$shipping_props[ 'shipping_' . $field ] = sanitize_text_field( wp_unslash( $_POST[ 'shipping_' . $field ] ) );
			}
		}

		// Set the properties.
		if ( ! empty( $billing_props ) ) {
			WC()->customer->set_props( $billing_props );
		}

		if ( ! empty( $shipping_props ) ) {
			WC()->customer->set_props( $shipping_props );
		}

		// Save to session.
		WC()->customer->save();

		// Return the data for verification.
		wp_send_json_success(
			array(
				'message'        => 'Customer data saved',
				'billing_props'  => $billing_props,
				'shipping_props' => $shipping_props,
				'saved_data'     => array(
					'billing_first_name' => WC()->customer->get_billing_first_name(),
					'billing_last_name'  => WC()->customer->get_billing_last_name(),
					'billing_email'      => WC()->customer->get_billing_email(),
					'billing_phone'      => WC()->customer->get_billing_phone(),
				),
			)
		);
	}
}
