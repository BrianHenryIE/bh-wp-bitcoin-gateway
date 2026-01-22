<?php
/**
 * Custom post type in WordPress, keyed with GUID of the wallet.
 *
 * TODO: Update the wp_post last modified time when updating metadata.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use InvalidArgumentException;
use WP_Post;

/**
 * Factory for creating Bitcoin_Wallet objects from WordPress posts.
 *
 * Converts wp_posts custom post types and their associated post_meta into typed Bitcoin_Wallet domain objects.
 */
class Bitcoin_Wallet_Factory {

	/**
	 * @param int $post_id The WordPress post id this wallet is stored under.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 * @throws UnknownCurrencyException If BTC is not correctly added to brick/money.
	 */
	public function get_by_wp_post_id( int $post_id ): Bitcoin_Wallet {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || Bitcoin_Wallet_WP_Post_Interface::POST_TYPE !== $post->post_type ) {
			throw new InvalidArgumentException( 'post_id ' . $post_id . ' is not a ' . Bitcoin_Wallet_WP_Post_Interface::POST_TYPE . ' post object' );
		}

		return $this->get_by_wp_post( $post );
	}

	/**
	 * Create a Bitcoin_Wallet object from a WordPress post + its post_meta.
	 *
	 * @param WP_Post $post The WordPress post representing the wallet.
	 * @return Bitcoin_Wallet The Bitcoin wallet object.
	 * @throws UnknownCurrencyException If BTC is not correctly added to brick/money.
	 */
	public function get_by_wp_post( WP_Post $post ): Bitcoin_Wallet {
		return new Bitcoin_Wallet(
			post_id: $post->ID,
			xpub: $post->post_title,
			status: Bitcoin_Wallet_Status::from( $post->post_status ),
			address_index: $this->get_address_index( $post ),
			balance: $this->get_balance( $post ),
		);
	}

	/**
	 * Get the current balance of this wallet, or null if it has never been checked.
	 *
	 * Must iterate across all addresses and sum them.
	 *
	 * @param WP_Post $post The WordPress post representing the wallet.
	 * @return Money|null The wallet balance or null if never checked.
	 * @throws UnknownCurrencyException If BTC is not correctly added to brick/money (or someone edited the db!).
	 */
	protected function get_balance( WP_Post $post ): ?Money {
		$balance = get_post_meta( $post->ID, Bitcoin_Wallet_WP_Post_Interface::BALANCE_META_KEY, true );
		if ( is_array( $balance ) && isset( $balance['amount'], $balance['currency'] ) ) {
			/** @var array{amount:string, currency:string} $balance  */
			return Money::of( ...$balance );
		}
		return null;
	}

	/**
	 * Get the index of the last generated address, so generating new addresses can start higher.
	 *
	 * @param WP_Post $post The WordPress post representing the wallet.
	 * @return int|null The index of the last derived address or null.
	 */
	protected function get_address_index( WP_Post $post ): ?int {
		$index = get_post_meta( $post->ID, Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY, true );
		return is_numeric( $index ) ? intval( $index ) : null; // Empty string '' will parse to 0.
	}
}
