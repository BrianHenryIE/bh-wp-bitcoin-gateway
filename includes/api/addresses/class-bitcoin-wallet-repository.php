<?php
/**
 * Save new Bitcoin wallets in WordPress, and fetch them via xpub or post id.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use Exception;
use WP_Post;
use wpdb;

/**
 * @see Bitcoin_Wallet_WP_Post_Interface
 */
class Bitcoin_Wallet_Repository {

	public function __construct(
		protected Bitcoin_Wallet_Factory $bitcoin_wallet_factory,
	) {
	}

	public function get_by_xpub( string $xpub ): ?Bitcoin_Wallet {
		$args = new Bitcoin_Wallet_Query(
			master_public_key: $xpub,
			status: Bitcoin_Wallet_Status::ALL,
		);

		// Only use query vars relevant to the query. This may be unnecessary.
		$query_array = $args->to_query_array();
		$query       = array_filter(
			$query_array,
			fn( string $key ): bool => in_array( $key, array( 'post_title', 'post_type', 'post_status' ), true ),
			ARRAY_FILTER_USE_KEY,
		);

		/** @var WP_Post[] $posts */
		$posts = get_posts( $query );
		if ( empty( $posts ) ) {
			return null;
		}
		if ( 1 === count( $posts ) ) {
			return $this->bitcoin_wallet_factory->get_by_wp_post( $posts[0] );
		}
		throw new Exception( 'TWO results found. Only one expected' );
	}

	/**
	 * Given the id of the wp_posts row storing the bitcoin wallet, return the typed Bitcoin_Wallet object.
	 *
	 * @param int $post_id WordPress wp_posts ID.
	 *
	 * @return Bitcoin_Wallet
	 * @throws Exception When the post_type of the post returned for the given post_id is not a Bitcoin_Wallet.
	 */
	public function get_by_wp_post_id( int $post_id ): Bitcoin_Wallet {

		return $this->bitcoin_wallet_factory->get_by_wp_post_id( $post_id );
	}

	public function get_all( $status = Bitcoin_Wallet_Status::ALL ): array {
		$args = new Bitcoin_Wallet_Query(
			status: $status,
		);

		$query_array = $args->to_query_array();
		$query       = array_filter(
			$query_array,
			fn( string $key ): bool => in_array( $key, array( 'post_type', 'post_status' ), true ),
			ARRAY_FILTER_USE_KEY,
		);

		/** @var WP_Post[] $posts */
		$posts = get_posts( $query );

		return array_map(
			fn( WP_Post $post ) => $this->bitcoin_wallet_factory->get_by_wp_post_id( $post->ID ),
			$posts
		);
	}

	/**
	 * Given a post_id,
	 *
	 * NB: post_name is 200 characters long. zpub is 111 characters.
	 *
	 * @param string $xpub The master public key of the wallet.
	 *
	 * @return int|null The wp_posts ID when it exists, or null when absent.
	 */
	public function get_post_id_for_wallet( string $xpub ): ?int {

		$post_id = wp_cache_get( $xpub, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );

		if ( is_numeric( $post_id ) ) {
			return (int) $post_id;
		}

		/**
		 * The WordPress wpdb object for database operations.
		 *
		 * TODO: Can this be replaced with a `get_posts( array( 'post_name' => $xpub, 'post_type' => Bitcoin_Wallet_WP_Post_Interface::POST_TYPE, 'numberposts' => 1 ) )` call?
		 *
		 * @var wpdb $wpdb
		 */
		global $wpdb;
		/**
		 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		 *
		 * @var int|bool $post_id
		 * @phpstan-ignore-next-line
		 */
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_name=%s", sanitize_title( $xpub ) ) );

		if ( false !== $post_id ) {
			$post_id = intval( $post_id );
			wp_cache_add( $xpub, $post_id, Bitcoin_Wallet_WP_Post_Interface::POST_TYPE );
		}

		return $post_id ?: null;
	}

	/**
	 * Create a new Bitcoin_Wallet WordPress post for the provided address and optionally specify the associated gateway.
	 *
	 * @param string  $master_public_key The xpub/ypub/zpub of the wallet.
	 * @param ?string $gateway_id The WC_Payment_Gateway the wallet is being used with.
	 *
	 * @return Bitcoin_Wallet The wp_posts saved wallet.
	 * @throws Exception When `wp_insert_post()` fails.
	 */
	public function save_new( string $master_public_key, ?string $gateway_id = null ): Bitcoin_Wallet {

		// TODO: Validate xpub, throw exception.

		$existing = $this->get_by_xpub( $master_public_key );
		if ( $existing ) {
			return $existing;
		}

		$args = new Bitcoin_Wallet_Query(
			master_public_key: $master_public_key,
			status: ! is_null( $gateway_id ) ? Bitcoin_Wallet_Status::ACTIVE : Bitcoin_Wallet_Status::INACTIVE,
			gateway_refs: $gateway_id ? array( $gateway_id ) : null,
		);

		$query_args_array = $args->to_query_array();
		$post_id          = wp_insert_post( $query_args_array, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( 'Failed to save new wallet as wp_post' );
		}

		return $this->get_by_wp_post_id( $post_id );
	}

	/**
	 * Save the index of the highest generated address.
	 *
	 * @param Bitcoin_Wallet $wallet The Bitcoin Wallet to indicate its newest derived address index.
	 * @param int            $index Nth address generated index.
	 */
	public function set_highest_address_index( Bitcoin_Wallet $wallet, int $index ): void {

		$this->update(
			$wallet,
			new Bitcoin_Wallet_Query(
				last_derived_address_index: $index,
			)
		);
	}

	protected function update(
		Bitcoin_Wallet $wallet,
		Bitcoin_Wallet_Query $query
	): void {
		$args            = $query->to_query_array();
		$args['post_id'] = $wallet->get_post_id();

		/** @var int|\WP_Error $result */
		$result = wp_update_post(
			$args
		);

		if ( ! is_wp_error( $result ) ) {
			return;
		}

		throw new \RuntimeException( $result->get_error_message() );
	}
}
