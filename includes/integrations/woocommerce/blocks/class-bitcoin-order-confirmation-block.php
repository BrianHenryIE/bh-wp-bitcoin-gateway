<?php
/**
 * WordPress block for Bitcoin Gateway order container.
 *
 * A container block that provides order context to inner blocks on WooCommerce Thank You pages.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Details_Formatter;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use WC_Order;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * @phpstan-type ParsedBlockWithoutSubblock array{blockName:string|null, attrs:array<mixed>, innerBlocks:null, innerHTML:string, innerContent:array<int,string|null>}
 * @phpstan-type ParsedBlock array{blockName:string|null, attrs:array<mixed>, innerBlocks:array<ParsedBlockWithoutSubblock>, innerHTML:string, innerContent:array<int,string|null>}
 */
class Bitcoin_Order_Confirmation_Block {

	/**
	 * Constructor.
	 *
	 * @param Settings_Interface         $settings Plugin settings, plugin url required for serving script.
	 * @param Bitcoin_Address_Repository $bitcoin_address_repository Repository for Bitcoin addresses.
	 */
	public function __construct(
		protected Settings_Interface $settings,
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
	) {
	}

	/**
	 *
	 * Hooking on `init` so even if WooCommerce is disabled, the blocks are still available for design.
	 *
	 * @hooked init
	 */
	public function register_block(): void {

		/** @var array{dependencies:array<string>, version:string} $webpack_asset */
		$webpack_asset = include $this->settings->get_plugin_dir() . 'assets/js/frontend/woocommerce/blocks/order-confirmation/bitcoin-order-confirmation-group/bitcoin-order-confirmation-group.min.asset.php';

		wp_register_script(
			'bh-wp-bitcoin-gateway-bitcoin-order-block',
			$this->settings->get_plugin_url() . 'assets/js/frontend/woocommerce/blocks/order-confirmation/bitcoin-order-confirmation-group/bitcoin-order-confirmation-group.min.js',
			$webpack_asset['dependencies'],
			$webpack_asset['version'],
			array( 'in_footer' => true )
		);

		$order_details = $this->get_order_details_formatted();

		$provides_context = array(
			'bh-wp-bitcoin-gateway/orderId' => 'orderId',
		);
		foreach ( (array) $order_details?->to_array( as_camel_case: true ) as $key => $value ) {
			if ( is_string( $value ) ) {
				$provides_context[ "bh-wp-bitcoin-gateway/$key" ] = $value;
			}
		}

		register_block_type(
			// TODO: rename to be explicitly a block for WooCommerce.
			'bh-wp-bitcoin-gateway/bitcoin-order',
			array(
				// TODO: Should this be `editor_script_handles` as an array?
				'editor_script'    => 'bh-wp-bitcoin-gateway-bitcoin-order-block',
				'attributes'       => array(
					'orderId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'provides_context' => $provides_context,
				'render_callback'  => array( $this, 'render_block' ),
			)
		);

		// TODO: move out of here for legibility.
		add_filter(
			'render_block_context',
			array( $this, 'add_order_id_context' ),
			10,
			3
		);
	}

	/**
	 * Render callback for the bitcoin-order block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block instance.
	 * @return string Rendered block content.
	 */
	public function render_block( array $attributes, string $content, $block ): string {
		$order_id = $attributes['orderId'] ?? 0;

		if ( 0 === $order_id ) {
			$order_id = $this->detect_order_id();
		}

		// Update the block context with the detected order ID.
		if ( $order_id > 0 ) {
			// TODO: This doesn't seem to do anything for PHP rendered blocks... does it help for JS rendered?
			$block->context['bh-wp-bitcoin-gateway/orderId'] = $order_id;
		}

		$order_details_formatted = $this->get_order_details_formatted()?->to_array( as_camel_case: true ) ?? array();
		foreach ( $order_details_formatted as $key => $value ) {
			$block->context[ "bh-wp-bitcoin-gateway/$key" ] = $value;
		}

		$wrapper_attributes = array(
			'class' => 'bh-wp-bitcoin-gateway-bitcoin-order-container',
		);
		/**
		 * TODO: we know these are strings, link to where they are set.
		 *
		 * @var string $key
		 * @var string $value
		 */
		foreach ( $block->context as $key => $value ) {
			$sanitized_key = str_replace( array( ':', '/' ), '-', $key );
			$wrapper_attributes[ 'data-context-' . $sanitized_key ] = esc_attr( (string) $value );
		}
		// Return the inner blocks content wrapped in our container.
		$wrapper_attributes_string = get_block_wrapper_attributes( $wrapper_attributes );

		/**
		 * TODO: I thought these would re-render the inner blocks, which were rendered before this block
		 * determined the order id it was wrapping them with.
		 *
		 * @see WP_Block::refresh_parsed_block_dependents()
		 * @see WP_Block::refresh_context_dependents()
		 */

		return sprintf(
			'<div %1$s><div class="wp-block-group"><div class="wp-block-group__inner-container">%2$s</div></div></div>',
			$wrapper_attributes_string,
			$content
		);
	}


	/**
	 * Get formatted order details for display.
	 *
	 * @return Details_Formatter|null The formatted order details or null if no order found.
	 */
	protected function get_order_details_formatted(): ?Details_Formatter {
		$order = $this->get_order();

		if ( is_null( $order ) ) {
			return null;
		}

		return new Details_Formatter(
			new WC_Bitcoin_Order(
				$order,
				$this->bitcoin_address_repository,
			)
		);
	}

	/**
	 * Get the WooCommerce order for the current request.
	 *
	 * @return WC_Order|null The WooCommerce order or null if not found.
	 */
	protected function get_order(): ?WC_Order {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null; // TODO: Add breakpoint and make sure this isn't executing unless it is needed.
		}
		$wc_order = wc_get_order( $this->detect_order_id() );
		return $wc_order instanceof WC_Order ? $wc_order : null;
	}

	/**
	 * Detect the current order ID from various WooCommerce sources.
	 *
	 * @return int Order ID or 0 if not found.
	 */
	protected function detect_order_id(): int {

		if ( isset( $GLOBALS['order-received'] ) && is_numeric( $GLOBALS['order-received'] ) ) {
			return absint( $GLOBALS['order-received'] );
		}

		// Check the key in the URL.
		if ( function_exists( 'wc_get_order_id_by_order_key' ) && isset( $_GET['key'] ) && is_numeric( $_GET['key'] ) ) {
			$order_id = wc_get_order_id_by_order_key( absint( $_GET['key'] ) );
			if ( $order_id > 0 ) {
				return $order_id;
			}
		}

		return 0;
	}

	/**
	 * Filter block context to add the order id.
	 *
	 * @hooked render_block_context
	 * @see render_block()
	 *
	 * `$parsed_block['innerBlocks'][]` is the same array shape as the array itself.
	 * TODO: Where is postID and postType set on the context?
	 *
	 * @param array{postId:int,postType:string} $context The current block context containing post ID and type from WordPress.
	 * @param array&ParsedBlock                 $parsed_block The parsed block array structure containing block name, attributes, and inner blocks from the WordPress block parser.
	 * @param ?WP_Block                         $parent_block The parent block instance if this block is nested, or null if it's a top-level block.
	 *
	 * @return array{postId:int,postType:string}|array{postId:int,'postType':string, "bh-wp-bitcoin-gateway/orderId":int}
	 */
	public function add_order_id_context( array $context, array $parsed_block, ?WP_Block $parent_block ): array {

		$block_name = $parsed_block['blockName'];

		// No need to check plain HTML or ourself.
		if ( is_null( $block_name ) || 'bh-wp-bitcoin-gateway/bitcoin-order' === $block_name ) {
			return $context;
		}

		// Check if the block uses our context.
		$block_uses_context = WP_Block_Type_Registry::get_instance()->get_registered( $block_name )?->get_uses_context() ?? array();

		if ( ! in_array( 'bh-wp-bitcoin-gateway/orderId', $block_uses_context, true ) ) {
			return $context;
		}

		$context['bh-wp-bitcoin-gateway/orderId'] = $this->detect_order_id();

		return $context;
	}
}
