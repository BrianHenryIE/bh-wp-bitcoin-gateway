<?php
/**
 * Add an admin submenu of the WooCommerce "Payments" menu item, linking to the Bitcoin gateway.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

/**
 * @see \Automattic\WooCommerce\Internal\Admin\Settings\PaymentsController::add_menu()
 */
class Menu {

	/**
	 * Constructor
	 *
	 * @param API_WooCommerce_Interface $api_woocommerce To get the list of Bitcoin gateways and their ids.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api_woocommerce,
	) {
	}

	/**
	 * @hooked admin_menu
	 */
	public function add_woocommerce_payments_submenu(): void {

		foreach ( $this->api_woocommerce->get_bitcoin_gateways() as $bitcoin_gateway ) {
			add_submenu_page(
				'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM',
				'Bitcoin Gateway',
				'Bitcoin',
				'manage_woocommerce',
				'admin.php?page=wc-settings&tab=checkout&section=' . $bitcoin_gateway->id
			);
		}
	}
}
