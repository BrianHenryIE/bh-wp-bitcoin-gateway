<?php
/**
 * Display generated addresses, their status and related orders.
 *
 * TODO: Add filters for status, wallet address.
 * TODO: Hijack Add New button to generate new addresses.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use Exception;
use WP_Post;

/**
 * Hooks into standard WP_List_Table actions and filters.
 *
 * @see wp-admin/edit.php?post_type=bh-bitcoin-address
 * @see WP_Posts_List_Table
 */
class Addresses_List_Table extends \WP_Posts_List_Table {

	/**
	 *
	 *
	 * @uses API_Interface::get_bitcoin_gateways()
	 */
	protected API_Interface&API_WooCommerce_Interface $api;

	/**
	 * Constructor
	 *
	 * @see _get_list_table()
	 *
	 * @param array{screen?:\WP_Screen} $args The data passed by WordPress.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );

		$post_type_name = $this->screen->post_type;

		/**
		 * Since this object is instantiated because it was defined when registering the post type, it's
		 * extremely unlikely the post type will not exist.
		 *
		 * @var \WP_Post_Type $post_type_object
		 */
		$post_type_object = get_post_type_object( $post_type_name );
		$this->api        = $post_type_object->plugin_objects['api'];

		add_filter( 'post_row_actions', array( $this, 'edit_row_actions' ), 10, 2 );
	}

	/**
	 * When rendering the wallet column, we will link to the gateways it is being used in.
	 *
	 * @var array<int, array<Bitcoin_Gateway>>
	 */
	protected array $wallet_id_to_gateways_map = array();

	/**
	 * Define the custom columns for the post type.
	 * Status|Order|Transactions|Received|Wallet|Derivation path.
	 *
	 * @return array<string, string> Column name : HTML output.
	 */
	public function get_columns() {
		$columns = parent::get_columns();

		$new_columns = array();
		foreach ( $columns as $key => $column ) {

			// Omit the "comments" column.
			if ( 'comments' === $key ) {
				continue;
			}

			// Add remaining columns after the Title column.
			$new_columns[ $key ] = $column;
			if ( 'title' === $key ) {

				$new_columns['status']               = 'Status';
				$new_columns['order_id']             = 'Order'; // TODO: Change to ~"assigned to".
				$new_columns['transactions_count']   = 'Transactions';
				$new_columns['received']             = 'Received';
				$new_columns['wallet']               = 'Wallet';
				$new_columns['derive_path_sequence'] = 'Path';
			}
			// The date column will be added last.
		}

		return $new_columns;
	}

	/**
	 * Given a WP_Post, get the corresponding Bitcoin_Address object, using a local array to cache for this request/object.
	 *
	 * @param WP_Post $post The post the address information is stored under.
	 *
	 * @return Bitcoin_Address
	 * @throws Exception When the post/post id does not match a bh-bitcoin-address cpt.
	 */
	protected function get_bitcoin_address_object( WP_Post $post ): Bitcoin_Address {
		$bitcoin_address_factory = new Bitcoin_Address_Factory();
		return $bitcoin_address_factory->get_by_wp_post( $post );
	}

	/**
	 * For now, let's link to an external site when the address is clicked.
	 * That way we can skip working on a single post view, and also provide an authoritative view of the address information.
	 *
	 * @param WP_Post $post The post this row is being rendered for.
	 *
	 * @return void Echos HTML.
	 */
	public function column_title( $post ) {
		ob_start();
		parent::column_title( $post );
		$render = (string) ob_get_clean();

		$bitcoin_address = $this->get_bitcoin_address_object( $post );

		$link = esc_url( "https://www.blockchain.com/btc/address/{$bitcoin_address->get_raw_address()}" );

		$render = (string) preg_replace( '/(.*<a.*)(href=")([^"]*)(".*>)/', '$1$2' . $link . '$4', $render, 1 );

		$render = (string) preg_replace( '/<a\s/', '<a target="_blank" ', $render, 1 );

		echo $render;
	}

	/**
	 *
	 * @param WP_Post $item The post this row is being rendered for.
	 *
	 * @return void Echos HTML.
	 */
	public function column_status( WP_Post $item ): void {

		$bitcoin_address = $this->get_bitcoin_address_object( $item );

		echo esc_html( $bitcoin_address->get_status()->value );
	}

	/**
	 *
	 * @param WP_Post $item The post this row is being rendered for.
	 *
	 * @return void Echos HTML.
	 */
	public function column_order_id( WP_Post $item ): void {

		$bitcoin_address = $this->get_bitcoin_address_object( $item );

		$order_id = $bitcoin_address->get_order_id();
		if ( ! is_null( $order_id ) ) {
			$url      = admin_url( "post.php?post={$order_id}&action=edit" );
			$order_id = (string) $order_id;
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( $order_id ) . '</a>';
		}
	}

	/**
	 *
	 * @param WP_Post $item The post this row is being rendered for.
	 *
	 * @return void Echos HTML.
	 */
	public function column_transactions_count( WP_Post $item ): void {

		$bitcoin_address = $this->get_bitcoin_address_object( $item );

		$transactions = $this->api->get_saved_transactions( $bitcoin_address );
		if ( is_array( $transactions ) ) {
			echo count( $transactions );
		} else {
			echo '';
		}
	}

	/**
	 *
	 * @param WP_Post $item The post this row is being rendered for.
	 *
	 * @return void Echos HTML.
	 */
	public function column_received( WP_Post $item ): void {

		$bitcoin_address = $this->get_bitcoin_address_object( $item );

		echo esc_html( $bitcoin_address->get_balance() ?? 'unknown' );
	}

	/**
	 * Print the HTML for the "wallet" column.
	 *
	 * Most sites will probably only ever use one wallet. Others might change wallet once or twice. Some will have
	 * multiple instances of the gateway running at the same time.
	 *
	 * TODO: Currently, this links to the WC_Payment_Gateway page. Maybe it should link externally like the
	 * title column?
	 *
	 * @param WP_Post $item The post this row is being rendered for.
	 *
	 * @return void Echos HTML.
	 */
	public function column_wallet( WP_Post $item ): void {

		try {
			$bitcoin_address = $this->get_bitcoin_address_object( $item );
		} catch ( Exception $exception ) {
			return;
		}

		$wallet_post_id = $bitcoin_address->get_wallet_parent_post_id();
		$wallet_post    = get_post( $wallet_post_id );
		if ( ! $wallet_post ) {
			// TODO: echo/log error.
			return;
		}
		$wallet_address = $wallet_post->post_excerpt;
		$abbreviated    = substr( $wallet_address, 0, 7 ) . '...' . substr( $wallet_address, -3 );

		// Is this wallet being used by a gateway?
		if ( ! isset( $this->wallet_id_to_gateways_map[ $wallet_post_id ] ) ) {
			$this->wallet_id_to_gateways_map[ $wallet_post_id ] = array_filter(
				$this->api->get_bitcoin_gateways(),
				function ( Bitcoin_Gateway $gateway ) use ( $wallet_address ): bool|null {
					return $gateway->get_xpub() === $wallet_address;
				}
			);
		}
		$gateways = $this->wallet_id_to_gateways_map[ $wallet_post_id ];

		$href_html = '';
		if ( 1 === count( $gateways ) ) {
			/** @var Bitcoin_Gateway $gateway */
			$gateway   = array_pop( $gateways );
			$href_html = '<a href="' . esc_url( admin_url( "admin.php?page=wc-settings&tab=checkout&section={$gateway->id}" ) ) . '">';
		}

		echo '<span title="' . esc_attr( $wallet_address ) . '">';
		echo wp_kses_post( $href_html );
		echo esc_html( $abbreviated );
		if ( ! empty( $href_html ) ) {
			echo '</a>'; }
		echo '</span>';
	}

	/**
	 * TODO: This should be sortable, and in theory should match the ID asc/desc sequence.
	 *
	 * @param WP_Post $item The post this row is being rendered for.
	 *
	 * @return void Echos HTML.
	 */
	public function column_derive_path_sequence( WP_Post $item ) {

		$bitcoin_address = $this->get_bitcoin_address_object( $item );

		$nth  = $bitcoin_address->get_derivation_path_sequence_number();
		$path = "0/$nth";
		echo esc_html( $path );
	}

	/**
	 * Remove edit and view actions, add an update action.
	 *
	 * TODO: add a click handler to the update (query for new transactions) action.
	 *
	 * @hooked post_row_actions
	 * @see \WP_Posts_List_Table::handle_row_actions()
	 *
	 * @param array<string,string> $actions Action id : HTML.
	 * @param WP_Post              $post     The post object.
	 *
	 * @return array<string,string>
	 */
	public function edit_row_actions( array $actions, WP_Post $post ): array {

		if ( Bitcoin_Address_WP_Post_Interface::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		unset( $actions['edit'] );
		unset( $actions['inline hide-if-no-js'] ); // "quick edit".
		unset( $actions['view'] );

		$actions['update_address'] = 'Update';

		return $actions;
	}
}
