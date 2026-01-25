<?php
/**
 * WordPress block for displaying the payment status of the WooCommerce order on the order confirmation page.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation;

use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;

/**
 * Register the `bh-wp-bitcoin-gateway/payment-status` block.
 *
 * @see assets/js/frontend/woocommerce/blocks/order-confirmation/payment-status/block.json
 */
class Bitcoin_Order_Payment_Status_Block {

	/**
	 * Constructor
	 *
	 * @param Settings_Interface $settings Plugin settings, used to determine the plugin dir.
	 */
	public function __construct(
		protected Settings_Interface $settings,
	) {
	}

	/**
	 * @hooked init
	 */
	public function register_block(): void {
		register_block_type(
			$this->settings->get_plugin_dir() . 'assets/js/frontend/woocommerce/blocks/order-confirmation/payment-status/'
		);
	}
}
