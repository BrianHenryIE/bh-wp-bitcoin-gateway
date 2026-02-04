<?php
/**
 * Display wallets in use/formerly in use, their status.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use WP_Post;
use WP_Post_Type;
use WP_Posts_List_Table;
use WP_Screen;

/**
 * Hooks into standard WP_List_Table actions and filters.
 *
 * @see wp-admin/edit.php?post_type=bh-bitcoin-wallet
 * @see WP_Posts_List_Table
 *
 * @phpstan-type Wallet_List_Table_Dependencies_Array array{api:API_Interface,bitcoin_wallet_repository:Bitcoin_Wallet_Repository}
 */
class Wallets_List_Table extends WP_Posts_List_Table {

	/**
	 * The main plugin functions.
	 *
	 * Not in use here currently.
	 */
	protected API_Interface $api;

	/**
	 * To get the full wallet details for display.
	 */
	protected Bitcoin_Wallet_Repository $bitcoin_wallet_repository;

	/**
	 * Constructor
	 *
	 * Retrieves dependencies from the WP_Post_Type object that were registered
	 * via Post_BH_Bitcoin_Wallet::register_wallet_post_type().
	 *
	 * @see _get_list_table()
	 *
	 * @param array{screen?:WP_Screen} $args The data passed by WordPress.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the post type object does not exist or does not have the dependencies this class needs.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );

		$post_type_name = $this->screen->post_type;

		/**
		 * Since this object is instantiated because it was defined when registering the post type, it's
		 * extremely unlikely the post type will not exist.
		 *
		 * @see Post_BH_Bitcoin_Wallet::$dependencies
		 * @see Post_BH_Bitcoin_Wallet::register_wallet_post_type()
		 *
		 * @var WP_Post_Type&object{dependencies:Wallet_List_Table_Dependencies_Array} $post_type_object
		 */
		$post_type_object = get_post_type_object( $post_type_name );

		// @phpstan-ignore-next-line function.impossibleType This could be null – I don't know how to use & in the type above with null.
		if ( is_null( $post_type_object ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Wallets_List_Table constructed before post type registered' );
		}
		if ( ! isset( $post_type_object->dependencies ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Wallets_List_Table constructed without required dependencies' );
		}

		$this->api                       = $post_type_object->dependencies['api'];
		$this->bitcoin_wallet_repository = $post_type_object->dependencies['bitcoin_wallet_repository'];

		add_filter( 'post_row_actions', array( $this, 'edit_row_actions' ), 10, 2 );
	}

	/**
	 * Define the custom columns for the post type.
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
	 * Given a WP_Post, get the corresponding Bitcoin_Wallet object (TODO: use a local array to cache.).
	 *
	 * @param WP_Post $post The post object for the current row.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the post is not a `bh-bitcoin-wallet` post type.
	 */
	protected function get_bitcoin_wallet_object( WP_Post $post ): Bitcoin_Wallet {
		return $this->bitcoin_wallet_repository->get_by_wp_post( $post );
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
	 * @see WP_Posts_List_Table::handle_row_actions()
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
