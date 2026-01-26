<?php
/**
 * Display wallets in use/formerly in use, their status.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use InvalidArgumentException;
use WP_Post;
use WP_Post_Type;
use WP_Posts_List_Table;
use WP_Screen;

/**
 * Hooks into standard WP_List_Table actions and filters.
 *
 * @see wp-admin/edit.php?post_type=bh-bitcoin-wallet
 * @see WP_Posts_List_Table
 */
class Wallets_List_Table extends WP_Posts_List_Table {

	/**
	 * The main plugin functions.
	 *
	 * Not in use here currently.
	 */
	protected API_Interface $api;

	/**
	 * Constructor
	 *
	 * @see _get_list_table()
	 *
	 * @param array{screen?:WP_Screen} $args The data passed by WordPress.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );

		$post_type_name = $this->screen->post_type;

		/**
		 * Since this object is instantiated because it was defined when registering the post type, it's
		 * extremely unlikely the post type will not exist.
		 *
		 * @see Post_BH_Bitcoin_Wallet::$plugin_objects
		 * @see Post_BH_Bitcoin_Wallet::register_wallet_post_type()
		 *
		 * @var WP_Post_Type&object{plugin_objects:array<string,API_Interface>} $post_type_object
		 */
		$post_type_object = get_post_type_object( $post_type_name );
		$this->api        = $post_type_object->plugin_objects['api'];

		add_filter( 'post_row_actions', array( $this, 'edit_row_actions' ), 10, 2 );
	}

	/**
	 * Define the custom columns for the post type.
	 *
	 * TODO: Only show the wallet column if there is more than one wallet.
	 *
	 * @return array<string, string> Column name : HTML output.
	 */
	public function get_columns() {
		/** @var non-empty-array<string,string> $columns */
		$columns = parent::get_columns();

		/** @var non-empty-array<string,string> $new_columns */
		$new_columns = array();
		foreach ( $columns as $key => $column ) {

			// Omit the "comments" column.
			if ( 'comments' === $key ) {
				continue;
			}

			// Add remaining columns after the Title column.
			$new_columns[ $key ] = $column;
			if ( 'title' === $key ) {

				$new_columns['status'] = 'Status';
			}
			// The date column will be added last.
		}

		return $new_columns;
	}

	/**
	 * @param WP_Post $post The post object for the current row.
	 *
	 * @throws InvalidArgumentException When the post is not a `bh-bitcoin-wallet` post type.
	 */
	protected function get_bitcoin_wallet_object( WP_Post $post ): Bitcoin_Wallet {
		$bitcoin_wallet_factory = new Bitcoin_Wallet_Factory();
		return $bitcoin_wallet_factory->get_by_wp_post( $post );
	}

	/**
	 * Print the status of this wallet.
	 *
	 * One of active|inactive.
	 *
	 * @see Post::register_wallet_post_type()
	 * @used-by WP_List_Table::single_row_columns()
	 *
	 * @param WP_Post $post The post this row is being rendered for.
	 */
	public function column_status( WP_Post $post ): void {
		$bitcoin_wallet = $this->get_bitcoin_wallet_object( $post );

		echo esc_html( $bitcoin_wallet->get_status()->value );
	}

	/**
	 * Remove edit and view actions, add an update action.
	 *
	 * TODO: add a click handler to the update action.
	 *
	 * @hooked post_row_actions
	 * @see \WP_Posts_List_Table::handle_row_actions()
	 *
	 * @param array<string,string> $actions Action id : HTML.
	 * @param WP_Post              $post    The post object.
	 *
	 * @return array<string,string>
	 */
	public function edit_row_actions( array $actions, WP_Post $post ): array {

		if ( Bitcoin_Wallet_WP_Post_Interface::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		unset( $actions['edit'] );
		unset( $actions['inline hide-if-no-js'] ); // "quick edit".
		unset( $actions['view'] );

		$actions['update_address'] = 'Update';

		return $actions;
	}
}
