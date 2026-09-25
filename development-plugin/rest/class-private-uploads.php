<?php
/**
 * REST endpoints to create and remove a probe file inside the private `logs` uploads directory, so a test can
 * confirm the directory really is private to administrators.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest;

use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

/**
 * `POST /wp-json/e2e-test-helper/v1/private_uploads/probe` writes the file and returns its URL and contents.
 * `DELETE` removes it.
 */
class Private_Uploads {

	const PROBE_FILENAME = 'e2e-private-uploads-probe.log';

	/**
	 * Add hooks to register the REST endpoints.
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
			'/private_uploads/probe',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => $this->create_probe( ... ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => $this->delete_probe( ... ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * The plugin's logs live in `wp-content/uploads/logs`, which bh-wp-private-uploads guards.
	 *
	 * @return array{path:string, url:string}
	 */
	protected function get_probe_location(): array {
		$upload_dir = wp_upload_dir();

		return array(
			'path' => trailingslashit( $upload_dir['basedir'] ) . 'logs/' . self::PROBE_FILENAME,
			'url'  => trailingslashit( $upload_dir['baseurl'] ) . 'logs/' . self::PROBE_FILENAME,
		);
	}

	/**
	 * Write a probe file containing a random secret the test can look for in responses.
	 */
	public function create_probe(): WP_REST_Response|WP_Error {
		$location = $this->get_probe_location();
		$secret   = 'e2e-secret-' . wp_generate_password( 24, false );

		wp_mkdir_p( dirname( $location['path'] ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $location['path'], "This log file must only be readable by administrators.\n{$secret}\n" ) ) {
			return new WP_Error( 'write_failed', 'Could not write the probe file.', array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'url'    => $location['url'],
				'path'   => $location['path'],
				'secret' => $secret,
			),
			200
		);
	}

	/**
	 * Remove the probe file.
	 */
	public function delete_probe(): WP_REST_Response {
		$location = $this->get_probe_location();

		if ( file_exists( $location['path'] ) ) {
			wp_delete_file( $location['path'] );
		}

		return new WP_REST_Response( array( 'deleted' => ! file_exists( $location['path'] ) ), 200 );
	}
}
