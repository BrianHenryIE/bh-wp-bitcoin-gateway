<?php
/**
 * Save new Bitcoin addresses in WordPress, and fetch them via xpub or post id.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use Exception;
use WP_Post;
use wpdb;

/**
 * Interface for creating/getting Bitcoin_Address objects stored in wp_posts table.
 *
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
 */
class Bitcoin_Address_Repository {

	public function __construct(
		protected Bitcoin_Address_Factory $bitcoin_address_factory,
	) {
	}

	/**
	 * Given a bitcoin master public key, get the WordPress post_id it is saved under.
	 *
	 * @param string $address Xpub|ypub|zpub.
	 *
	 * @return int|null The post id if it exists, null if it is not found.
	 */
	public function get_post_id_for_address( string $address ): ?int {

		$post_id = wp_cache_get( $address, Bitcoin_Address_WP_Post_Interface::POST_TYPE );

		if ( is_numeric( $post_id ) ) {
			return (int) $post_id;
		}

		/**
		 * WordPress database object.
		 *
		 * TODO: Can this be replaced with a `get_posts()` call?
		 *
		 * @var wpdb $wpdb
		 */
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// @phpstan-ignore-next-line
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name=%s", sanitize_title( $address ) ) );

		if ( is_numeric( $post_id ) ) {
			$post_id = intval( $post_id );
			wp_cache_add( $address, $post_id, Bitcoin_Address_WP_Post_Interface::POST_TYPE );
			return $post_id;
		}

		return null;
	}

	/**
	 * Given the id of the wp_posts row storing the bitcoin address, return the typed Bitcoin_Address object.
	 *
	 * @param int $post_id WordPress wp_posts ID.
	 *
	 * @return Bitcoin_Address
	 * @throws Exception When the post_type of the post returned for the given post_id is not a Bitcoin_Address.
	 */
	public function get_by_post_id( int $post_id ): Bitcoin_Address {
		return $this->bitcoin_address_factory->get_by_wp_post_id( $post_id );
	}

	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_assigned_bitcoin_addresses(): array {
		return $this->get_addresses(
			new Bitcoin_Address_Query(
				status: Bitcoin_Address_Status::ASSIGNED,
				numberposts: 200,
			)
		);
	}

	/**
	 * Check do we have at least 1 assigned address, i.e. an address waiting for transactions.
	 *
	 * Across all wallets.
	 */
	public function has_assigned_bitcoin_addresses(): bool {
		return ! empty(
			$this->get_addresses(
				new Bitcoin_Address_Query(
					status: Bitcoin_Address_Status::ASSIGNED,
					numberposts: 1,
				)
			)
		);
	}

	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_unknown_bitcoin_addresses(): array {
		// 'orderby'        => 'ID',
		// 'order'          => 'ASC',
		// TODO: Should this query use ID.asc as a way to order?
		// TODO: updated_at is probably correct.

		return $this->get_addresses(
			new Bitcoin_Address_Query(
				status: Bitcoin_Address_Status::UNKNOWN,
			)
		);
	}


	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_addresses( Bitcoin_Address_Query $filter ): array {

		return array_map(
			fn( WP_Post $wp_post ) => $this->bitcoin_address_factory->get_by_wp_post( $wp_post ),
			get_posts( $filter->to_query_array() )
		);
	}

	/**
	 * Wrapper on wp_insert_post(), sets the address as the post_title, post_excerpt and post_name.
	 *
	 * @throws Exception When WordPress fails to create the wp_post.
	 */
	public function save_new( Bitcoin_Address_Query $query ): int {

		// TODO: Validate address, throw exception.

		/** @var WpUpdatePostArray $args */
		$args = $query->to_query_array();

		$post_id = wp_insert_post( $args, true );

		if ( is_wp_error( $post_id ) ) {
			// TODO Log.
			throw new Exception( 'WordPress failed to create a post for the wallet.' );
		}

		// TODO: Maybe start a background job to check for transactions. Where is best to do that?

		return $post_id;
	}


	/**
	 * Find addresses generated from this wallet which are unused and return them as `Bitcoin_Address` objects.
	 *
	 * TODO: Maybe this shouldn't be in here?
	 *
	 * @return Bitcoin_Address[]
	 */
	public function get_fresh_addresses(): array {
		$posts = get_posts(
			array(
				// 'post_parent'    => $this->post->ID,
												'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
				'post_status'    => Bitcoin_Address_Status::UNUSED->value,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			)
		);
		return array_map(
			function ( WP_Post $post ) {
				return $this->bitcoin_address_factory->get_by_wp_post( $post );
			},
			$posts
		);
	}
}
