<?php
/**
 * DTO to accept primitive data in the constructor, map those variable names to wp_post fields, to get an array for
 * `get_post()` etc. queries.
 *
 * Enums are parsed to their backing value.
 * Money uses {@see Money::jsonSerialize()} to store it as a readable JSON object.
 *
 * Extend this class to suit a specific post_type; use `::to_query_array()` in calls to `update_post()` etc.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Queries;

use BackedEnum;
use InvalidArgumentException;

/**
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_type?:string, post_status?:string, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string, meta_input?:array<string,mixed>}
 */
abstract readonly class WP_Post_Query_Abstract {

	/**
	 * @see Bitcoin_Address_WP_Post_Interface::POST_TYPE
	 */
	public string $post_type;

	/**
	 * Override this with the pieces of data to be saved to the WP_Post.
	 */
	public function __construct() {
		$this->post_type = $this->get_post_type();
	}

	/**
	 * Set the `post_type` for the query.
	 */
	abstract protected function get_post_type(): string;

	/**
	 * Map data from object variable name to WP_Post field name.
	 *
	 * @see self::get_valid_keys()
	 *
	 * @return array<string,mixed> $map field_name : variable
	 */
	protected function get_wp_post_fields(): array {
		return array();
	}

	/**
	 * Map data from object variable name to post_meta key name.
	 *
	 * TODO: Document behavior: updates or appends?!
	 *
	 * @return array<string,mixed> meta_key : meta_value.
	 */
	abstract protected function get_meta_input(): array;

	/**
	 * @return string[] List of valid field in the WP_Query.
	 *
	 * TODO: Mark which fields are indexed.
	 */
	protected function get_valid_keys(): array {
		return array(
			'post_type',
			'post_name',
			'post_content',
			'post_excerpt',
			'post_title',
			'post_status',
			'post_parent',
			'numberposts',
			'orderby',
			'order',
			'posts_per_page',
			'meta_input',
		);
	}

	/**
	 * TODO: need a convention for excluding fields that the caller knows aren't important/helpful.
	 *
	 * @return WpUpdatePostArray&array<string, mixed>
	 * @throws InvalidArgumentException When an unknown field is used.
	 */
	public function to_query_array(): array {

		// TODO: are the field names case sensitive?
		$wp_post_fields = $this->get_wp_post_fields();

		foreach ( array_keys( $wp_post_fields ) as $field_name ) {
			if ( ! in_array( $field_name, $this->get_valid_keys(), true ) ) {
				throw new InvalidArgumentException( 'Invalid key: ' . $field_name );
			}
		}

		$wp_post_fields['post_type'] = $this->post_type;

		$mapper = function ( $value ) {
			if ( $value instanceof BackedEnum ) {
				return $value->value;
			}
			if ( $value instanceof \BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money ) {
				return $value->jsonSerialize();
			}
			if ( is_array( $value ) ) {
				return wp_json_encode( $value );
			}
			// TODO: if DateTimeInterface serialize as something legible, if we're using it.
			return $value;
		};

		/** @var WpUpdatePostArray $wp_post_fields */
		$wp_post_fields = array_map(
			$mapper,
			// Remove empty values. TODO: should this check `null` and allow empty strings?
			array_filter( $wp_post_fields )
		);

		$wp_post_fields['meta_input'] = array_map(
			$mapper,
			array_filter( $this->get_meta_input() )
		);

		if ( empty( $wp_post_fields['meta_input'] ) ) {
			unset( $wp_post_fields['meta_input'] );
		}

		return $wp_post_fields;
	}
}
