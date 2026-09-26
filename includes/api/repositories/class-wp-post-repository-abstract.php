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
	 * `wp_insert_post()` and `wp_update_post()` expect slashed data and call `wp_unslash()` on it (including each
	 * `meta_input` value), so anything containing a backslash, e.g. a namespaced class name stored as a string or
	 * inside JSON, must be slashed exactly once, here, at the boundary. Callers pass raw values.
	 *
	 * @param array<string,mixed> $args Post arguments as built by a query object.
	 *
	 * @phpstan-param WpUpdatePostArray $args
	 * @phpstan-return WpUpdatePostArray
	 *
	 * @return array<string,mixed>
	 */
	protected static function slash_for_wordpress( array $args ): array {
		/** @var WpUpdatePostArray $slashed */
		$slashed = wp_slash( $args );
		return $slashed;
	}

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

		/** @var WpUpdatePostArray $args */
		$args       = $query->to_query_array();
		$args['ID'] = $model->get_post_id();

		/**
		 * Only the query's own fields and meta are written; `meta_input` keys that are absent are left untouched
		 * by WordPress. Re-reading all existing meta and passing it back through `wp_update_post()` (as this once
		 * did) unslashed it a second time, stripping the backslashes from stored class names such as the
		 * address's `integration_id`, after which the WooCommerce integration no longer recognised its own
		 * addresses and orders were never marked paid.
		 */

		/** @var int<1, max>|WP_Error $result */
		$result = wp_update_post(
			self::slash_for_wordpress( $args ),
			wp_error: true
		);

		if ( ! is_wp_error( $result ) ) {
			return; // TODO: Should we return the refreshed object, `$this->get_by_wp_post_id( $result )`.
		}

		throw new RuntimeException( esc_html( $result->get_error_message() ) );
	}
}
