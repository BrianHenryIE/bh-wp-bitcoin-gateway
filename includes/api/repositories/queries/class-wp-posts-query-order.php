<?php
/**
 * Criteria for ordering and limiting WP Post queries.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries;

/**
 * @see get_posts()
 */
readonly class WP_Posts_Query_Order {

	/**
	 * Constructor.
	 *
	 * @param ?int    $count The number of posts to return in the query (max 200).
	 * @param ?string $order_by Which field to order the results by.
	 * @param ?string $order_direction Order the results ASC or DESC.
	 */
	public function __construct(
		public ?int $count = null,
		public ?string $order_by = null,
		public ?string $order_direction = null,
	) {
	}

	/**
	 * @return array{order?:string,numberposts?:int,orderby?:string}
	 */
	public function to_query_array(): array {
		$query_args = array();

		if ( ! is_null( $this->count ) ) {
			$query_args['numberposts'] = $this->count;
		}
		if ( ! is_null( $this->order_by ) ) {
			$query_args['orderby'] = $this->order_by;
		}
		if ( ! is_null( $this->order_direction ) ) {
			$query_args['order'] = $this->order_direction;
		}

		return $query_args;
	}
}
