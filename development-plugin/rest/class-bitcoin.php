<?php
/**
 * Some Bitcoin REST endpoints that will hopefully be refined and included/moved later.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\WooCommerce_Integration;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * List wallets, delete wallets and addresses.
 */
class Bitcoin {

	/**
	 * Add hooks to register the REST endpoint/s.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', $this->register_bitcoin_wallets_routes( ... ) );
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
				'callback'            => $this->list_bitcoin_wallets( ... ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'e2e-test-helper/v1',
			'/bitcoin_wallets',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => $this->reset_bitcoin_data( ... ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'e2e-test-helper/v1',
			'/bitcoin_wallets/seed',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => $this->seed_wallet_addresses( ... ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'xpub'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'count' => array(
						'default'           => 1,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Create the wallet for a master public key and derive its first `count` addresses, marking them "unused"
	 * WITHOUT checking the blockchain.
	 *
	 * Lets a test place an order against a known, already-funded address: the plugin would otherwise see the
	 * existing payment, mark the address "used", and never assign it. The plugin's own pending background jobs
	 * are cancelled so they do not re-check (and re-mark) the addresses before the test's checkout.
	 *
	 * @param WP_REST_Request $request The request, with `xpub` and optional `count` params.
	 */
	public function seed_wallet_addresses( WP_REST_Request $request ): WP_REST_Response {
		/** @var API_Interface $api */
		$api = $GLOBALS['bh_wp_bitcoin_gateway'];

		/** @var string $xpub */
		$xpub  = $request->get_param( 'xpub' );
		$count = max( 1, (int) $request->get_param( 'count' ) );

		$wallet = $api->get_or_save_wallet_for_master_public_key(
			$xpub,
			array(
				'integration' => WooCommerce_Integration::class,
				'gateway_id'  => 'bh_bitcoin',
			)
		)->wallet;

		$generated = $api->generate_new_addresses_for_wallet( $wallet, $count );

		$addresses = array();
		foreach ( $generated->new_addresses as $address ) {
			wp_update_post(
				array(
					'ID'          => $address->get_post_id(),
					'post_status' => Bitcoin_Address_Status::UNUSED->value,
				)
			);
			$addresses[] = array(
				'post_id'                         => $address->get_post_id(),
				'address'                         => $address->get_raw_address(),
				'derivation_path_sequence_number' => $address->get_derivation_path_sequence_number(),
			);
		}

		foreach ( array(
			Background_Jobs_Actions_Interface::SINGLE_ENSURE_UNUSED_ADDRESSES_HOOK,
			Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK,
			Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK,
			Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK,
		) as $hook ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook );
			}
		}

		return new WP_REST_Response(
			array(
				'wallet_post_id' => $wallet->get_post_id(),
				'addresses'      => $addresses,
			),
			200
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
			static fn( \WP_Post $post ): array => array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'name'   => $post->post_name,
				'status' => $post->post_status,
			),
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

		$wallet_posts = get_posts(
			array(
				'post_type'   => Bitcoin_Wallet_WP_Post_Interface::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
		foreach ( $wallet_posts as $post ) {
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
					count( ... ),
					$deleted
				),
			),
			200
		);
	}
}
