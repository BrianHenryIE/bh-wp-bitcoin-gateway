<?php
/**
 * Mostly takes a WP_Post and returns a Bitcoin_Transaction
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use InvalidArgumentException;
use JsonException;
use WP_Post;

/**
 * @phpstan-type MoneySerializedArray array{amount:string,currency:string}
 */
class Bitcoin_Transaction_Factory {

	/**
	 * @param int $post_id The WordPress post id this wallet is stored under.
	 *
	 * @throws InvalidArgumentException When the supplied post_id is not a post of this type.
	 * @throws JsonException
	 */
	public function get_by_wp_post_id( int $post_id ): Bitcoin_Transaction {
		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || Bitcoin_Transaction_WP_Post_Interface::POST_TYPE !== $post->post_type ) {
			throw new InvalidArgumentException( 'post_id ' . $post_id . ' is not a ' . Bitcoin_Transaction_WP_Post_Interface::POST_TYPE . ' post object' );
		}

		return $this->get_by_wp_post( $post );
	}

	/**
	 * Takes a WP_Post and gets the values (primitives?) to create a Bitcoin_Transaction.
	 *
	 * TODO: Transactions.
	 *
	 * @param WP_Post $post The backing WP_Post for this Bitcoin_Transaction.
	 *
	 * @throws JsonException
	 */
	public function get_by_wp_post( WP_Post $post ): Bitcoin_Transaction {

		$post_content = json_decode( $post->post_content, true, 512, JSON_THROW_ON_ERROR );

		$hash    = $post_content['hash'];
		$version = $post_content['version'];
		$v_in    = $post_content['v_in'];
		/** TODO: parse to {@see Transaction_VOut} */
		$v_out = $post_content['v_out'];

		return new Bitcoin_Transaction(
			post: $post,
			block_height: get_post_meta( $post->ID, Bitcoin_Transaction_WP_Post_Interface::BLOCK_HEIGHT_META_KEY, true ),
			block_time: get_post_meta( $post->ID, Bitcoin_Transaction_WP_Post_Interface::BLOCK_DATETIME_META_KEY, true ),
			tx_id: $post->post_title,
			hash: $hash,
			version: $version,
			v_in: $v_in,
			v_out: $v_out,
		);
	}
}
