<?php
/**
 * Add a custom post type for Bitcoin wallet.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Wallets_List_Table
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use WP_Query;

/**
 * Register the custom post types with WordPress.
 *
 * @see register_post_type()
 * @see register_post_status()
 *
 * @see wp-admin/edit.php?post_type=bh-bitcoin-wallet
 */
class Post_BH_Bitcoin_Wallet {

	/**
	 * Array of plugin objects to pass to post types.
	 *
	 * @var array{api:API_Interface} $plugin_objects
	 */
	protected array $plugin_objects = array();

	/**
	 * Constructor
	 *
	 * @param API_Interface $api The main plugin functions.
	 */
	public function __construct( API_Interface $api ) {
		$this->plugin_objects['api'] = $api;
	}

	/**
	 * Registers the bh-bitcoin-wallet post type and its statuses.
	 *
	 * @hooked init
	 */
	public function register_wallet_post_type(): void {

		$labels = array(
			'name'          => _x( 'Bitcoin Wallets', 'post type general name', 'bh-wp-bitcoin-gateway' ),
			'singular_name' => _x( 'Bitcoin Wallet', 'post type singular name', 'bh-wp-bitcoin-gateway' ),
			'menu_name'     => 'Bitcoin Wallets',
		);

		$args = array(
			'labels'         => $labels,
			'description'    => 'Wallets used with WooCommerce Bitcoin gateways.',
			'public'         => true,
			'menu_position'  => 8,
			'supports'       => array( 'title', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'    => false,
			'show_in_menu'   => false,
			'plugin_objects' => $this->plugin_objects,
		);

		register_post_type( Bitcoin_Wallet_WP_Post_Interface::POST_TYPE, $args );

		register_post_status(
			'active',
			array(
				'label'                     => _x( 'Active', 'post', 'bh-wp-bitcoin-gateway' ),
				'public'                    => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s is the number of Bitcoin wallets that are in use. */
				'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>' ),
			)
		);

		register_post_status(
			'inactive',
			array(
				'label'                     => _x( 'Inactive', 'post', 'bh-wp-bitcoin-gateway' ),
				'public'                    => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s is the number of Bitcoin wallets that have been created but are not currently in use. */
				'label_count'               => _n_noop( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>' ),
			)
		);
	}
}
