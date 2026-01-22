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

		$as_array = (array) $this;

		$map_from_to = array(
			'order_by'        => 'orderby',
			'order_direction' => 'order',
			'count'           => 'numberposts',
		);

		foreach ( $map_from_to as $from => $to ) {
			if ( isset( $as_array[ $from ] ) ) {
				$as_array[ $to ] = $as_array[ $from ];
				unset( $as_array[ $from ] );
			}
		}

		/** @var array{order?:string,numberposts?:int,orderby?:string} $as_array */
		return array_filter( $as_array );
	}
}
