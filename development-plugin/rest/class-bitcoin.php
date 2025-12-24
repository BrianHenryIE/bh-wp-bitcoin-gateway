<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_WP_Post_Interface;
use Exception;
use WP_REST_Response;
use WP_REST_Server;

class Bitcoin {
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_bitcoin_wallets_routes' ) );
	}

	/**
	 * Register REST routes for listing and resetting Bitcoin wallet/address posts.
	 *
	 * @hooked rest_api_init
	 */
	public function register_bitcoin_wallets_routes(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/bitcoin_wallets',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_bitcoin_wallets' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'e2e-test-helper/v1',
			'/bitcoin_wallets',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'reset_bitcoin_data' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * List Bitcoin wallet posts with minimal fields useful for tests.
	 */
	public function list_bitcoin_wallets(): WP_REST_Response {
		$wallet_posts = get_posts(
			array(
				'post_type'   => Bitcoin_Wallet_WP_Post_Interface::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);

		$result = array_map(
			static function ( \WP_Post $post ): array {
				return array(
					'id'     => $post->ID,
					'title'  => $post->post_title,
					'name'   => $post->post_name,
					'status' => $post->post_status,
				);
			},
			$wallet_posts
		);

		return new WP_REST_Response(
			array(
				'count' => count( $result ),
				'data'  => $result,
			),
			200
		);
	}

	/**
	 * Delete all Bitcoin wallet and address posts.
	 */
	public function reset_bitcoin_data(): WP_REST_Response {
		$deleted = array(
			Bitcoin_Wallet_WP_Post_Interface::POST_TYPE  => array(),
			Bitcoin_Address_WP_Post_Interface::POST_TYPE => array(),
		);

		/**
		 * For some reason, when mass-deleting, the checkout page was being deleted.
		 *
		 * `wc_get_page_id('checkout')`
		 *
		 * @see wc_get_page_id()
		 */
		$checkout_page_id = (int) get_option( 'woocommerce_checkout_page_id' );

		$wallet_posts = get_posts(
			array(
				'post_type'   => Bitcoin_Wallet_WP_Post_Interface::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
		foreach ( $wallet_posts as $post ) {
			if ( $post->ID === $checkout_page_id ) {
				throw new Exception( 'somehow the bitcoin wallet wp_post id is the checkout wp_post  id?' );
			}
			if ( wp_delete_post( $post->ID, true ) ) {
				$deleted[ Bitcoin_Wallet_WP_Post_Interface::POST_TYPE ][] = $post->ID;
			}
		}

		$address_posts = get_posts(
			array(
				'post_type'   => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
		foreach ( $address_posts as $post ) {
			if ( $post->ID === $checkout_page_id ) {
				throw new Exception( 'somehow the bitcoin address wp_post id is the checkout wp_post  id?' );
			}
			if ( wp_delete_post( $post->ID, true ) ) {
				$deleted[ Bitcoin_Address_WP_Post_Interface::POST_TYPE ][] = $post->ID;
			}
		}

		$wallet_posts_after = get_posts(
			array(
				'post_type'   => Bitcoin_Wallet_WP_Post_Interface::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);

		return new WP_REST_Response(
			array(
				'message'       => 'Bitcoin wallet/address posts reset.',
				'deleted'       => $deleted,
				'deleted_count' => array_map(
					fn( array $post_ids ) => count( $post_ids ),
					$deleted
				),
			),
			200
		);
	}
}
