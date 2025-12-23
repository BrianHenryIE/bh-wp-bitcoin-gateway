<?php
/**
 * Plugin Name:       Bitcoin Gateway Development Plugin
 * Description:       Convenience, demo and test helper functions.
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wp-bitcoin-gateway/
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin;

use ActionScheduler;
use ActionScheduler_Abstract_RecurringSchedule;
use ActionScheduler_Action;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_WP_Post_Interface;
use Exception;
use JsonException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// TODO check for stray requests: exchange rate query seems to be happening too frequently.
// https://api-pub.bitfinex.com/v2/tickers?symbols=tBTCUSD

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


// Add a "Customer Order Link" to the order admin page.

/**
 * @hooked admin_footer
 */
function order_link(): void {
	global $pagenow;
	if ( 'post.php' !== $pagenow ) {
		return;
	}
	if ( ! isset( $_GET['post'] ) ) {
		return;
	}
	$post_id = absint( $_GET['post'] );

	$post_type = get_post_type( $post_id );

	if ( 'shop_order' !== $post_type ) {
		return;
	}

	/** @var \WC_Order $wc_order */
	$wc_order = wc_get_order( absint( $_GET['post'] ) );
	$link     = $wc_order->get_checkout_order_received_url();

	$script = <<<EOT
jQuery('.woocommerce-order-data__heading').append('<span style="display: inline-block;"><a class="customer_order_link" title="Customer order link" target="_blank" href="$link">Customer Order Link</a></span>');
EOT;
	$style  = <<<EOT
.customer_order_link {
  color: #333; margin: 1.33em 0 0;
  width: 14px;
  height: 0;
  padding: 14px 0 0;
  margin: 0 0 0 6px;
  overflow: hidden;
  position: relative;
  color: #999;
  border: 0;
  float: right;
}
.customer_order_link::after {
  font-family: Dashicons;
  content: "\\f504";
  position: absolute;
  top: 0;
  left: 0;
  text-align: center;
  vertical-align: top;
  line-height: 14px;
  font-size: 14px;
  font-weight: 400;
}
EOT;

	echo '<script>' . $script . '</script>';
	echo '<style>' . $style . '</style>';
}
add_action( 'admin_footer', __NAMESPACE__ . '\order_link' );


( new \BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\E2E_Test_Helper_Plugin() )->register_hooks();

class E2E_Test_Helper_Plugin {

	public function register_hooks(): void {
		add_filter( 'rest_pre_dispatch', array( $this, 'show_settings_in_rest' ) );
		/**
		 * @see \Automattic\WooCommerce\StoreApi\Routes\V1\AbstractCartRoute::check_nonce()
		 */
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		add_filter( 'rest_authentication_errors', array( $this, 'set_rest_user_admin' ) );

		add_action( 'init', array( $this, 'login_as_any_user' ) );

		/**
		 * @see \Automattic\WooCommerce\Internal\Admin\Onboarding\OnboardingSetupWizard::do_admin_redirects()
		 */
		add_filter( 'woocommerce_prevent_automatic_wizard_redirect', '__return_true' );

		add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );
		add_action( 'rest_api_init', array( $this, 'bh_activate_theme' ) );
		add_action( 'rest_api_init', array( $this, 'register_get_theme_list_route' ) );
		add_action( 'rest_api_init', array( $this, 'register_test_helper_rest_active_theme_route' ) );

		add_action( 'rest_api_init', array( $this, 'register_action_scheduler_search' ) );
		add_action( 'rest_api_init', array( $this, 'register_action_scheduler_delete' ) );
		add_action( 'rest_api_init', array( $this, 'register_bitcoin_wallets_routes' ) );
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

	/**
	 * @param WP_Error|null|true $errors WP_Error if authentication error, null if authentication method wasn't used, true if authentication succeeded.
	 *
	 * @see WP_REST_Server::check_authentication()
	 * @hooked rest_authentication_errors
	 */
	public function set_rest_user_admin( $errors ): mixed {

		wp_set_current_user( 1 );

		return $errors;
	}

	/**
	 * @hooked init
	 */
	public function login_as_any_user(): void {
		if ( isset( $_GET['login_as_user'] ) ) {
			$login_as_user = sanitize_text_field( $_GET['login_as_user'] );
			/** @var \WP_User|false $wp_user */
			$wp_user = get_user_by( 'slug', $login_as_user );
			if ( ! $wp_user ) {
				throw new \Exception( 'Could not find user: ' . $login_as_user );
			}
			wp_set_current_user( $wp_user->ID );
			wp_set_current_user( $wp_user->ID, $wp_user->user_login );
			wp_set_auth_cookie( $wp_user->ID );
		}
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

	/**
	 * Add a REST endpoint for searching Action Scheduler actions.
	 *
	 * GET /wp-json/e2e-test-helper/v1/action_scheduler/search?hook={$hook}
	 *
	 * @hooked rest_api_init
	 */
	public function register_action_scheduler_search(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/action_scheduler/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'action_scheduler_search' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Search for Action Scheduler schedule events.
	 */
	public function action_scheduler_search( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		if ( ! function_exists( 'as_supports' ) ) {
			return new WP_Error( '', 'Action scheduler is not loaded.', array( 'status' => 500 ) );
		}

		$search = $request->get_params();

		// if ( ! $search ) {
		// return new WP_Error( 'rest_missing_param', 'Missing "s" search parameter.', array( 'status' => 400 ) );
		// }

		/**
		 * @see ActionScheduler_DBStore::get_query_actions_sql()
		 */
		$search['per_page'] = $search['per_page'] ?? 200;
		$search['orderby']  = $search['orderby'] ?? 'date';
		$search['order']    = $search['order'] ?? 'ASC';
		$results            = as_get_scheduled_actions( $search );

		$store = ActionScheduler::store();

		/**
		 * @see \ActionScheduler_ListTable::prepare_items()
		 */
		$action_scheduler_action_to_array = function ( ActionScheduler_Action $action, int $index ) use ( $store ) {
			$schedule   = $action->get_schedule();
			$recurrence = $schedule instanceof ActionScheduler_Abstract_RecurringSchedule
				? $schedule->get_recurrence()
				: null;

			return array(
				'id'             => $index,
				'hook'           => $action->get_hook(),
				'status'         => $store->get_status( $index ),
				'args'           => $action->get_args(),
				'group'          => $action->get_group(),
				/**
				 * Might be nice to use @see ActionScheduler_ListTable::human_interval()
				 */
				'recurrence'     => $recurrence,
				'scheduled_date' => $action->get_schedule()?->next(),
				// 'log'
				'schedule'       => $action->get_schedule(),
				'hook_priority'  => $action->get_priority(),
			);
		};

		foreach ( $results as $index => $result ) {
			$results[ $index ] = $action_scheduler_action_to_array( $result, $index );
		}

		return new WP_REST_Response(
			array(
				'message' => 'Action Scheduler search results for: ' . str_replace( array( "\r", "\n", "\t" ), '', print_r( $search, true ) ),
				'count'   => count( $results ),
				'data'    => $results,
			),
			200
		);
	}

	/**
	 * Add a REST endpoint for deleting Action Scheduler actions.
	 *
	 * DELETE /wp-json/e2e-test-helper/v1/action_scheduler/{$id}
	 *
	 * @hooked rest_api_init
	 */
	public function register_action_scheduler_delete(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/action_scheduler/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'action_scheduler_delete' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Delete an Action Scheduler scheduled task by id (int).
	 */
	public function action_scheduler_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		if ( ! function_exists( 'as_supports' ) ) {
			return new WP_Error( '', 'Action scheduler is not loaded.', array( 'status' => 500 ) );
		}

		/** @var string $id */
		$id = $request->get_param( 'id' );

		if ( ! $id ) {
			return new WP_Error( 'rest_missing_param', 'Missing id parameter.', array( 'status' => 400 ) );
		}

		/** @var \ActionScheduler_Store $store */
		$store = ActionScheduler::store();

		$claim_id = $store->get_claim_id( $id );

		$as = $store->fetch_action( $id );

		if ( ! ( $as instanceof ActionScheduler_Action ) ) {
			return new WP_Error( 'rest_invalid_param', 'Invalid id: ' . $id, array( 'status' => 400 ) );
		}

		try {
			$store->delete_action( $id );
		} catch ( Exception $exception ) {
			return new WP_Error( 'rest_error', 'Invalid id: ' . $id . ' – ' . $exception->getMessage(), array( 'status' => 500 ) );
		}
		$claim_id_after = $store->get_claim_id( $id );

		return new WP_REST_Response(
			array(
				'message' => 'Action Scheduler delete ' . $id,
				'result'  => $claim_id !== $claim_id_after ? 'deleted' : 'not found',
				'success' => ! $claim_id_after,
			),
			200
		);
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
		$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );

		$wallet_posts = get_posts(
			array(
				'post_type'   => Bitcoin_Wallet_WP_Post_Interface::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);
		foreach ( $wallet_posts as $post ) {
			if ( $post->ID == $checkout_page_id ) {
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
			if ( $post->ID == $checkout_page_id ) {
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



function wpgraphql_wpenv_fix_url( $url, $path = '' ) {

	$internal_url = preg_replace( '#(https?://(localhost|127.0.0.1)):888[8,9]#', '$1', $url );

	if ( 'wp-cron.php' === $path || wp_doing_cron() ) {
		$url_for_cron_hostname = 'http://' . get_option( 'wp_env_cron_hostname' ) . '/wp-cron.php';
		return $internal_url;
	}

	if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
		$url_for_cron_hostname = 'http://' . get_option( 'wp_env_cron_hostname' );
		return $internal_url;
	}

	return $url;
}
add_filter( 'site_url', 'BrianHenryIE\\WP_Bitcoin_Gateway\\Development_Plugin\\wpgraphql_wpenv_fix_url', 1, 2 );
add_filter( 'home_url', 'BrianHenryIE\\WP_Bitcoin_Gateway\\Development_Plugin\\wpgraphql_wpenv_fix_url', 1, 2 );
add_filter( 'wp_login_url', 'BrianHenryIE\\WP_Bitcoin_Gateway\\Development_Plugin\\wpgraphql_wpenv_fix_url', 1, 2 );
add_filter( 'admin_url', 'BrianHenryIE\\WP_Bitcoin_Gateway\\Development_Plugin\\wpgraphql_wpenv_fix_url', 1, 2 );
