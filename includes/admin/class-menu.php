<?php
/**
 * Add an admin submenu of the WooCommerce "Payments" menu item, linking to the Bitcoin gateway.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;

/**
 * This could be improved by using {@see API_WooCommerce_Interface::get_bitcoin_gateways()}.
 */
class Menu {

	/**
	 * @hooked admin_menu
	 */
	public function add_woocommerce_payments_submenu(): void {

		// TODO: check current page is not `admin.php?page=wc-settings&tab=checkout&section=bh_bitcoin`.

		add_submenu_page(
			'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM',
			'Bitcoin Gateway',
			'Bitcoin',
			'manage_woocommerce',
			'admin.php?page=wc-settings&tab=checkout&section=bitcoin_gateway'
		);
	}
}
