<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BackedEnum;

class Bitcoin_Address_Query {

	/**
	 * @see Bitcoin_Address_WP_Post_Interface::POST_TYPE
	 */
	public readonly string $post_type;

	/**
	 * @var array<string,string> $map to:from
	 */
	protected array $map = array(
		'post_name'    => 'xpub',
		'post_excerpt' => 'xpub',
		'post_title'   => 'xpub',
		'post_status'  => 'status',
		'post_parent'  => 'wp_post_parent_id',
	);

	/**
	 * @var string[]
	 */
	protected array $valid_keys = array(
		'post_type',
		'post_name',
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

	/**
	 * @var array<string,string>
	 */
	protected array $meta_keys_map = array(
		Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY => 'derivation_path_sequence_index',
	);

	/**
	 * @var array<string,string>
	 */
	protected array $valid_meta_keys = array(
		Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY,
	);

	/**
	 * TODO: Use Bitcoin_Wallet objects where appropriate.
	 */
	public function __construct(
		public ?int $wp_post_parent_id = null,
		public ?Bitcoin_Address_Status $status = null,
		public ?string $xpub = null,
		public ?int $numberposts = null,
		public ?string $orderby = null,
		public ?string $order = null,
		public ?int $posts_per_page = null,
		public ?int $derivation_path_sequence_index = null,
	) {
		$this->post_type = Bitcoin_Address_WP_Post_Interface::POST_TYPE;
	}

	/**
	 * TODO: move this to a parent class.
	 * TODO: use getters.
	 * TODO: PhpStan array shape.
	 */
	public function to_query_array(): array {

		$object_vars = get_object_vars( $this );

		unset( $object_vars['map'] );

		foreach ( $this->map as $key => $value ) {
			if ( isset( $object_vars[ $value ] ) ) {
				$object_vars[ $key ] = $object_vars[ $value ];
			}
		}

		$object_vars['meta_input'] = array();

		foreach ( $this->meta_keys_map as $key => $value ) {
			if ( isset( $object_vars[ $value ] ) ) {
				$object_vars['meta_input'][ $key ] = $object_vars[ $value ];
				unset( $object_vars[ $value ] );
			}
		}

		foreach ( $object_vars as $key => $value ) {
			if ( ! in_array( $key, $this->valid_keys ) ) {
				unset( $object_vars[ $key ] );
			}
		}

		$args = array_map(
			fn( $value ) => $value instanceof BackedEnum ? $value->value : $value,
			array_filter( $object_vars )
		);
		return $args;
	}
}
