<?php
/**
 * Common documentation for repositories backed by WP_Post.
 *
 * And shared functions (mostly to convert WP_Error into an exception).
 *
 * @see WP_Post_Query_Abstract
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries\WP_Post_Query_Abstract;
use RuntimeException;
use WP_Error;

/**
 * PHP doesn't have generics to neatly require: (TODO: what is the PHPStan syntax for these generics)
 *
 * @method get_by_wp_post_id( int $post_id )
 * @method get_all( $status ): array
 *
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string, meta_input?:array<string,mixed>}
 */
abstract class WP_Post_Repository_Abstract {

	/**
	 * Run `wp_update_post()` (after setting the post id); throw on failure.
	 *
	 * TODO: This should return the object.
	 *
	 * @param Bitcoin_Wallet|Bitcoin_Address|Bitcoin_Transaction $model To get the ID to update.
	 * @param WP_Post_Query_Abstract                             $query A map from the model's properties to WP_Query arguments.
	 *
	 * @throws RuntimeException On `wp_update_post()` failure.
	 */
	protected function update(
		Bitcoin_Wallet|Bitcoin_Address|Bitcoin_Transaction $model,
		WP_Post_Query_Abstract $query
	): void {

		/** @var array<int|string,mixed> $existing_meta */
		$existing_meta = get_post_meta( $model->get_post_id() );

		/** @var WpUpdatePostArray $args */
		$args       = $query->to_query_array();
		$args['ID'] = $model->get_post_id();

		$new_meta = $args['meta_input'] ?? array();

		// Flatten the `get_post_meta()` result which treats all keys as not-single values.
		$args['meta_input'] = array();
		foreach ( $existing_meta as $key => $value ) {
			if ( is_array( $value ) && count( $value ) === 1 && array_key_first( $value ) === 0 ) {
				$args['meta_input'][ $key ] = $value[0];
			} else {
				$args['meta_input'][ $key ] = $value;
			}
		}
		// Meta keys are string values; `array_merge()` re-indexes integer keys but that should be irrelevant here.
		$args['meta_input'] = array_merge( $args['meta_input'], $new_meta );
		if ( empty( $args['meta_input'] ) ) {
			unset( $args['meta_input'] );
		}

		/** @var int|WP_Error $result */
		$result = wp_update_post(
			$args
		);

		if ( ! is_wp_error( $result ) ) {
			return; // TODO: Should we return the refreshed object, `$this->get_by_wp_post_id( $result )`.
		}

		throw new RuntimeException( $result->get_error_message() );
	}
}
