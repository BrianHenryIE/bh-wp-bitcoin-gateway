<?php
/**
 * Mostly takes a WP_Post and returns a Bitcoin_Address
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use InvalidArgumentException;
use WP_Post;

/**
 * @phpstan-type MoneySerializedArray array{amount:string,currency:string}
 */
class Bitcoin_Address_Factory {

	/**
	 * @param int $post_id The WordPress post id this wallet is stored under.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 */
	public function get_by_wp_post_id( int $post_id ): Bitcoin_Address {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || Bitcoin_Address_WP_Post_Interface::POST_TYPE !== $post->post_type ) {
			throw new InvalidArgumentException( 'post_id ' . $post_id . ' is not a ' . Bitcoin_Address_WP_Post_Interface::POST_TYPE . ' post object' );
		}

		return $this->get_by_wp_post( $post );
	}

	/**
	 * Takes a WP_Post and gets the values (primitives?) to create a Bitcoin_Address.
	 *
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Address.
	 */
	public function get_by_wp_post( WP_Post $post ): Bitcoin_Address {

		$bitcoin_address = new Bitcoin_Address(
			post_id: $post->ID,
			wallet_parent_post_id: $post->post_parent,
			raw_address: $post->post_excerpt,
			derivation_path_sequence_number: ( function () use ( $post ) {
				/** @var array|bool|float|int|resource|string|null|mixed $meta_value */
				$meta_value = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY, true );
				return is_numeric( $meta_value ) ? intval( $meta_value ) : null;
			} )(),
			status: Bitcoin_Address_Status::from( $post->post_status ),
			target_amount: ( function () use ( $post ): ?Money {
				/** @var MoneySerializedArray|array{} $target_amount_meta */
				$target_amount_meta = array_filter( (array) get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, true ) );
				return empty( $target_amount_meta ) ? null : Money::of( ...$target_amount_meta );
			} )(),
			order_id: ( function () use ( $post ): ?int {
				/** @var array|bool|float|int|resource|string|null|mixed $order_id_meta */
				$order_id_meta = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::ORDER_ID_META_KEY, true );
				return is_numeric( $order_id_meta ) ? intval( $order_id_meta ) : null;
			} )(),
			tx_ids: ( function () use ( $post ): ?array {
				/** @var array|null|mixed $tx_ids_meta */
				$tx_ids_meta = get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::TRANSACTIONS_META_KEY, true );
				return is_array( $tx_ids_meta ) ? $tx_ids_meta : null;
			} )(),
			balance: ( function () use ( $post ): ?Money {
				/** @var MoneySerializedArray|array{} $balance_meta */
				$balance_meta = array_filter( (array) get_post_meta( $post->ID, Bitcoin_Address_WP_Post_Interface::BALANCE_META_KEY, true ) );
				return empty( $balance_meta ) ? null : Money::of( ...$balance_meta );
			} )(),
		);

		return $bitcoin_address;
	}
}
