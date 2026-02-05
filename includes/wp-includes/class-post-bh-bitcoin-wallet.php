<?php
/**
 * Add a custom post type for Bitcoin wallet.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Wallets_List_Table
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Wallets_List_Table;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;

/**
 * Register the custom post types with WordPress.
 *
 * @see register_post_type()
 * @see register_post_status()
 *
 * @see Wallets_List_Table
 * @see wp-admin/edit.php?post_type=bh-bitcoin-wallet
 *
 * @see Bitcoin_Wallet_WP_Post_Interface
 *
 * @phpstan-import-type Wallet_List_Table_Dependencies_Array from Wallets_List_Table
 */
class Post_BH_Bitcoin_Wallet {

	/**
	 * Dependencies to pass to the WP_Post_Type object for use by the list table.
	 *
	 * Stored on the post type args and accessible via:
	 * `get_post_type_object( 'bh-bitcoin-wallet' )->dependencies`
	 *
	 * @see Wallets_List_Table::__construct() Consumer of these dependencies.
	 *
	 * @var array&Wallet_List_Table_Dependencies_Array $dependencies
	 */
	protected array $dependencies;

	/**
	 * Constructor
	 *
	 * @param API_Interface             $api The main plugin API for wallet operations.
	 * @param Bitcoin_Wallet_Repository $bitcoin_wallet_repository To get the wallet details for the admin list table.
	 */
	public function __construct(
		API_Interface $api,
		Bitcoin_Wallet_Repository $bitcoin_wallet_repository,
	) {
		$this->dependencies = array(
			'api'                       => $api,
			'bitcoin_wallet_repository' => $bitcoin_wallet_repository,
		);
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
			'labels'        => $labels,
			'description'   => 'Wallets used with WooCommerce Bitcoin gateways.',
			'public'        => true,
			'menu_position' => 8,
			'supports'      => array( 'title', 'thumbnail', 'excerpt', 'comments' ),
			'has_archive'   => false,
			'show_in_menu'  => false,
			'show_in_rest'  => false, // TODO: change to `true` after ensuring correct authorization.
			/**
			 * Dependencies passed to the list table via the WP_Post_Type object.
			 *
			 * @see Wallets_List_Table::__construct() Where these are consumed.
			 */
			'dependencies'  => $this->dependencies,
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
