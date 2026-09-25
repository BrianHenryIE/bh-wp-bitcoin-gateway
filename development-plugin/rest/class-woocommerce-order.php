<?php
/**
 * REST endpoint to backdate a WooCommerce order, for tests that pair an order with a historic payment.
 *
 * `wc/v3` accepts `date_created` when creating an order but ignores it on update.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `PUT /wp-json/e2e-test-helper/v1/orders/{id}/date_created` with `{ "date_created": "2026-09-24T00:00:00" }`.
 */
class WooCommerce_Order {

	/**
	 * Add hooks to register the REST endpoint.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', $this->register_routes( ... ) );
	}

	/**
	 * @hooked rest_api_init
	 */
	public function register_routes(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/orders/(?P<id>\d+)/date_created',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => $this->set_date_created( ... ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'           => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'date_created' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Set the order's creation date (interpreted in the site timezone, as WooCommerce does).
	 *
	 * @param WP_REST_Request $request The request, with `id` and `date_created` params.
	 */
	public function set_date_created( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$order = wc_get_order( absint( $request->get_param( 'id' ) ) );

		if ( ! $order ) {
			return new WP_Error( 'not_found', 'Order not found.', array( 'status' => 404 ) );
		}

		/** @var string $date_created */
		$date_created = $request->get_param( 'date_created' );

		$order->set_date_created( $date_created );
		$order->save();

		return new WP_REST_Response(
			array(
				'id'               => $order->get_id(),
				'date_created'     => $order->get_date_created()?->format( 'c' ),
				'date_created_gmt' => $order->get_date_created()?->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'c' ),
			),
			200
		);
	}
}
