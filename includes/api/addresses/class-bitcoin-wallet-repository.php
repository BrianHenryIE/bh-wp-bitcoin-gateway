<?php
/**
 * Save new Bitcoin wallets in WordPress, and fetch them via xpub or post id.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use Exception;
use InvalidArgumentException;
use WP_Post;
use wpdb;

/**
 * @see Bitcoin_Wallet_WP_Post_Interface
 */
class Bitcoin_Wallet_Repository extends WP_Post_Repository_Abstract {

	public function __construct(
		protected Bitcoin_Wallet_Factory $bitcoin_wallet_factory,
	) {
	}

	/**
	 * NB: post_name is 200 characters long. zpub is 111 characters.
	 *
	 * @param string $xpub The master public key of the wallet.
	 */
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
		throw new BH_WP_Bitcoin_Gateway_Exception( count( $posts ) . ' Bitcoin_Wallets found, only one expected, for ' . $xpub );
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

	/**
	 *
	 * @param Bitcoin_Wallet_Status $status Filter by Bitcoin_Wallet_Status – 'active'|'inactive'.
	 *
	 * @return Bitcoin_Wallet[]
	 */
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
			fn( WP_Post $post ) => $this->bitcoin_wallet_factory->get_by_wp_post( $post ),
			$posts
		);
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
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Failed to save new wallet as wp_post' );
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
			model: $wallet,
			query: new Bitcoin_Wallet_Query(
				last_derived_address_index: $index,
			)
		);
	}
}
