<?php
/**
 * Add a custom post type for Bitcoin transaction.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Transactions_List_Table
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;

/**
 * Register the custom post types with WordPress.
 *
 * @see register_post_type()
 * @see register_post_status()
 *
 * @see wp-admin/edit.php?post_type=bh-bitcoin-transaction
 */
class Post_BH_Bitcoin_Transaction {

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
	 * Registers the bh-bitcoin-transaction post type and its statuses.
	 *
	 * @hooked init
	 */
	public function register_transaction_post_type(): void {

		$labels = array(
			'name'          => _x( 'Bitcoin Transactions', 'post type general name', 'bh-wp-bitcoin-gateway' ),
			'singular_name' => _x( 'Bitcoin Transaction', 'post type singular name', 'bh-wp-bitcoin-gateway' ),
			'menu_name'     => 'Bitcoin Transactions',
		);
		$args   = array(
			'labels'         => $labels,
			'description'    => 'Transactions used with WooCommerce Bitcoin gateways.',
			'public'         => true,
			'menu_position'  => 8,
			'supports'       => array( 'title', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'    => false,
			'show_in_menu'   => false,
			'plugin_objects' => $this->plugin_objects,
			'show_in_rest'   => true,
		);
		register_post_type( Bitcoin_Transaction_WP_Post_Interface::POST_TYPE, $args );
	}
}
