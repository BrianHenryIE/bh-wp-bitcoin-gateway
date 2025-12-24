<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use RuntimeException;

/**
 * PHP doesn't have generics to neatly require:
 *
 * @method get_by_wp_post_id( int $post_id )
 * @method get_all( $status ): array
 */
abstract class WP_Post_Repository_Abstract {

	protected function update(
		Bitcoin_Wallet|Bitcoin_Address|Bitcoin_Transaction $object,
		WP_Post_Query_Abstract $query
	): void {
		$args       = $query->to_query_array();
		$args['ID'] = $object->get_post_id();

		/** @var int|\WP_Error $result */
		$result = wp_update_post(
			$args
		);

		if ( ! is_wp_error( $result ) ) {
			return;
		}

		throw new RuntimeException( $result->get_error_message() );
	}
}
