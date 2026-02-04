<?php
/**
 * Add a custom post type for Bitcoin address.
 * Will have statuses 'unused', 'used', 'assigned'.
 * Will have postmeta for:
 * * its derive path
 * * which order it is for
 * * its transactions
 * * its total received amount
 * Its parent will be its xpub.
 *
 * WP_List_Table can show all addresses and their orders and amount received and last activity date.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use WP_Query;

/**
 * Register the custom post types with WordPress.
 *
 * @see _get_list_table()
 *
 * @see register_post_type()
 * @see register_post_status()
 *
 * @see Addresses_List_Table
 * @see wp-admin/edit.php?post_type=bh-bitcoin-address
 *
 * @phpstan-import-type Address_List_Table_Dependencies_Array from Addresses_List_Table
 */
class Post_BH_Bitcoin_Address {

	/**
	 * The post type name for Bitcoin addresses.
	 */
	public const POST_TYPE = 'bh-bitcoin-address';

	/**
	 * Dependencies to pass to the WP_Post_Type object for use by the list table.
	 *
	 * Stored on the post type args and accessible via:
	 * `get_post_type_object( 'bh-bitcoin-address' )->dependencies`
	 *
	 * @see Addresses_List_Table::__construct() Consumer of these dependencies.
	 * @see self::get_dependencies_schema() For the expected array structure.
	 *
	 * @var array&Address_List_Table_Dependencies_Array $dependencies
	 */
	protected array $dependencies = array();

	/**
	 * Constructor
	 *
	 * @param API_Interface              $api The main plugin API for address operations (transactions, etc.).
	 * @param Bitcoin_Address_Repository $bitcoin_address_repository To get the address details for the admin list table.
	 * @param Bitcoin_Wallet_Repository  $bitcoin_wallet_repository To get the wallet integration details in the list table view.
	 */
	public function __construct(
		API_Interface $api,
		Bitcoin_Address_Repository $bitcoin_address_repository,
		Bitcoin_Wallet_Repository $bitcoin_wallet_repository,
	) {
		$this->dependencies['api']                        = $api;
		$this->dependencies['bitcoin_address_repository'] = $bitcoin_address_repository;
		$this->dependencies['bitcoin_wallet_repository']  = $bitcoin_wallet_repository;
	}

	/**
	 * Registers the bh-bitcoin-address post type and its statuses.
	 *
	 * @hooked init
	 */
	public function register_address_post_type(): void {

		$labels = array(
			'name'          => _x( 'Bitcoin Addresses', 'post type general name', 'bh-wp-bitcoin-gateway' ),
			'singular_name' => _x( 'Bitcoin Address', 'post type singular name', 'bh-wp-bitcoin-gateway' ),
			'menu_name'     => 'Bitcoin Addresses',
		);
		$args   = array(
			'labels'        => $labels,
			'description'   => 'Addresses used with WooCommerce Bitcoin gateways.',
			'public'        => true,
			'menu_position' => 8,
			'supports'      => array( 'title', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'   => false,
			'show_in_menu'  => false,
			'show_in_rest'  => true,
			/**
			 * Dependencies passed to the list table via the WP_Post_Type object.
			 *
			 * @see Addresses_List_Table::__construct() Where these are consumed.
			 */
			'dependencies'  => $this->dependencies,
		);
		register_post_type( Bitcoin_Address_WP_Post_Interface::POST_TYPE, $args );

		register_post_status(
			Bitcoin_Address_Status::UNKNOWN->value,
			array(
				'post_type'                 => array( Bitcoin_Address_WP_Post_Interface::POST_TYPE ),
				'label'                     => _x( 'Unknown', 'post' ),
				'public'                    => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s is the number of Bitcoin addresses whose status is unknown. */
				'label_count'               => _n_noop( 'Unknown <span class="count">(%s)</span>', 'Unknown <span class="count">(%s)</span>' ),
			)
		);

		register_post_status(
			Bitcoin_Address_Status::UNUSED->value,
			array(
				'post_type'                 => array( Bitcoin_Address_WP_Post_Interface::POST_TYPE ),
				'label'                     => _x( 'Unused', 'post' ),
				'public'                    => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s is the number of Bitcoin addresses that have yet to be used. */
				'label_count'               => _n_noop( 'Unused <span class="count">(%s)</span>', 'Unused <span class="count">(%s)</span>' ),
			)
		);

		register_post_status(
			Bitcoin_Address_Status::ASSIGNED->value,
			array(
				'post_type'                 => array( Bitcoin_Address_WP_Post_Interface::POST_TYPE ),
				'label'                     => _x( 'Assigned', 'post' ),
				'public'                    => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s is the number of Bitcoin addresses that have been assigned. */
				'label_count'               => _n_noop( 'Assigned <span class="count">(%s)</span>', 'Assigned <span class="count">(%s)</span>' ),
			)
		);

		register_post_status(
			Bitcoin_Address_Status::USED->value,
			array(
				'post_type'                 => array( Bitcoin_Address_WP_Post_Interface::POST_TYPE ),
				'label'                     => _x( 'Used', 'post' ),
				'public'                    => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s is the number of Bitcoin addresses that have been used. */
				'label_count'               => _n_noop( 'Used <span class="count">(%s)</span>', 'Used <span class="count">(%s)</span>' ),
			)
		);
	}

	/**
	 * If the query is for bh-bitcoin-address posts, set post_status to all statuses, unless another is specified.
	 *
	 * Otherwise, `get_posts()` and the REST API return no posts.
	 *
	 * @see get_posts()
	 * @hooked parse_query
	 * @see WP_Query::get_posts()
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 */
	public function add_post_statuses( WP_Query $query ): void {

		if ( 'bh-bitcoin-address' === ( $query->query['post_type'] ?? false )
			&& 'publish' === ( $query->query['post_status'] ?? false )
			) {
				$query->query_vars['post_status'] = 'all';
		}
	}
}
