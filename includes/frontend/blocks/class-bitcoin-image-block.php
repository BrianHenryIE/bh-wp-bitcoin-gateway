<?php
/**
 * Extend the image block to always show to Bitcoin title image.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Frontend\Blocks;

use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use WP_Block_Type;

/**
 * @phpstan-type BlockVariationArray array{name:string,title:string,description:string,isDefault?:bool,attributes:array<mixed>,isActive?:array<string>,scope?:array<string>}
 */
class Bitcoin_Image_Block {

	/**
	 * Constructor
	 *
	 * @param Settings_Interface $settings Plugin settings, used to determine the plugin url.
	 */
	public function __construct(
		protected Settings_Interface $settings,
	) {
	}

	/**
	 * Add a core/image variation that uses the Bitcoin logo.
	 *
	 * /wp-content/plugins/bh-wp-bitcoin-gateway/assets/bitcoin.png
	 *
	 * @hooked get_block_type_variations
	 * @see WP_Block_Type::get_variations()
	 *
	 * @param array<BlockVariationArray> $variations The existing block variations registered for this block type.
	 * @param WP_Block_Type              $block_type The WordPress block type object being filtered.
	 *
	 * @return array<BlockVariationArray> Array of block variations.
	 */
	public function add_bitcoin_image_variation( array $variations, WP_Block_Type $block_type ): array {

		if ( 'core/image' !== $block_type->name ) {
			return $variations;
		}

		$image_url = plugins_url( 'assets/bitcoin.png', $this->settings->get_plugin_basename() );

		$variations[] = array(
			'name'        => 'bh-bitcoin-image',
			'title'       => __( 'Bitcoin image', 'bh-wp-bitcoin-gateway' ),
			'description' => __( 'The Bitcoin logo', 'bh-wp-bitcoin-gateway' ),
			/** TODO: look up what scope:inserter is here. */
			'isDefault'   => false,
			'attributes'  => array(
				'url' => $image_url,
			),
		);

		return $variations;
	}
}
