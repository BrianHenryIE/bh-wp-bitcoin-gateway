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
 */
class Bitcoin_Address_Repository {

	/**
	 * Given a bitcoin master public key, get the WordPress post_id it is saved under.
	 *
	 * @param string $address Xpub|ypub|zpub.
	 *
	 * @return int|null The post id if it exists, null if it is not found.
	 */
	public function get_post_id_for_address( string $address ): ?int {

		$post_id = wp_cache_get( $address, Bitcoin_Address::POST_TYPE );

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

		if ( ! is_null( $post_id ) ) {
			$post_id = intval( $post_id );
			wp_cache_add( $address, $post_id, Bitcoin_Address::POST_TYPE );
		}

		return $post_id;
	}

	/**
	 * Wrapper on wp_insert_post(), sets the address as the post_title, post_excerpt and post_name.
	 *
	 * @param string         $address The Bitcoin address.
	 * @param int            $address_index The derivation sequence number.
	 * @param Bitcoin_Wallet $wallet The wallet whose xpub this address was derived from.
	 *
	 * @return int The new post_id.
	 *
	 * @throws Exception When WordPress fails to create the wp_post.
	 */
	public function save_new( string $address, int $address_index, Bitcoin_Wallet $wallet ): int {

		// TODO: Validate address, throw exception.

		$args = array(
			'post_type'    => Bitcoin_Address::POST_TYPE,
			'post_name'    => sanitize_title( $address ), // An indexed column.
			'post_excerpt' => $address,
			'post_title'   => $address,
			'post_status'  => Bitcoin_Address_Status::UNKNOWN->value,
			'post_parent'  => $wallet->get_post_id(),
			'meta_input'   => array(
				Bitcoin_Address::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY => $address_index,
			),
		);

		$post_id = wp_insert_post( $args, true );

		if ( is_wp_error( $post_id ) ) {
			// TODO Log.
			throw new Exception( 'WordPress failed to create a post for the wallet.' );
		}

		// TODO: Maybe start a background job to check for transactions. Where is best to do that?

		return $post_id;
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
		return new Bitcoin_Address( $post_id );
	}

	/**
	 * @param Bitcoin_Address_Status $post_staus Status unused|assigned|etc. that should be returned.
	 * @param int                    $number_posts Defaults to WP_Query's max of 200.
	 *
	 * @return WP_Post[]
	 */
	protected function get_bitcoin_address_posts( Bitcoin_Address_Status $post_staus, int $number_posts = 200 ): array {
		$assigned_addresses = get_posts(
			array(
				'post_type'   => Bitcoin_Address::POST_TYPE,
				'post_status' => $post_staus->value,
				'orderby'     => 'post_modified',
				'order'       => 'ASC',
				'numberposts' => $number_posts,
			)
		);
		return $assigned_addresses;
	}

	/**
	 * @return WP_Post[]
	 */
	public function get_assigned_bitcoin_addresses_wp_posts(): array {
		return $this->get_bitcoin_address_posts( Bitcoin_Address_Status::ASSIGNED );
	}

	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_assigned_bitcoin_addresses(): array {
		return array_map(
			fn( WP_Post $bitcoin_address_wp_post ) => new Bitcoin_Address( $bitcoin_address_wp_post->ID ),
			$this->get_bitcoin_address_posts( Bitcoin_Address_Status::ASSIGNED )
		);
	}

	/**
	 * Check do we have at least 1 assigned address, i.e. an address waiting for transactions.
	 */
	public function has_assigned_bitcoin_addresses(): bool {
		$assigned_addresses = $this->get_bitcoin_address_posts( Bitcoin_Address_Status::ASSIGNED, 1 );
		return ! empty( $assigned_addresses );
	}

	/**
	 * @return Bitcoin_Address[]
	 */
	public function get_unknown_bitcoin_addresses(): array {
		return array_map(
			fn( WP_Post $bitcoin_address_wp_post ) => new Bitcoin_Address( $bitcoin_address_wp_post->ID ),
			$this->get_bitcoin_address_posts( Bitcoin_Address_Status::UNKNOWN )
		);
	}
}
