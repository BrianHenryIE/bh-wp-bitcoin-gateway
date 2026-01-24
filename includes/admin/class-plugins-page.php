<?php
/**
 * The plugin page output of the plugin.
 * Adds a "Settings" link
 * Adds an "Orders" link when Filter WooCommerce Orders by Payment Method plugin is installed.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Admin;

use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;

/**
 * Adds items to the plugin's row on plugins.php.
 *
 * @see \WP_Plugins_List_Table
 */
class Plugins_Page {
	/**
	 * Constructor
	 *
	 * @param Settings_Interface $settings The plugin's settings.
	 */
	public function __construct(
		protected Settings_Interface $settings, // The plugin basename is needed when checking with plugin's row meta is being filtered.
	) {
	}

	/**
	 * Adds 'Settings' link to the configuration under WooCommerce's payment gateway settings page.
	 *
	 * @hooked plugin_action_links_{plugin basename}
	 *
	 * @param string[] $links_array The links that will be shown below the plugin name on plugins.php (usually "Deactivate").
	 *
	 * @return string[]
	 * @see \WP_Plugins_List_Table::display_rows()
	 */
	public function add_settings_action_link( array $links_array ): array {

		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return $links_array;
		}

		/**
		 * Default gateway id.
		 *
		 * @see Bitcoin_Gateway::$id
		 */
		$gateway_id = 'bh_bitcoin';

		$setting_link   = admin_url( "admin.php?page=wc-settings&tab=checkout&section={$gateway_id}" );
		$plugin_links   = array();
		$plugin_links[] = '<a href="' . $setting_link . '">' . __( 'Settings', 'bh-wp-bitcoin-gateway' ) . '</a>';

		return array_merge( $plugin_links, $links_array );
	}

	/**
	 * Adds 'Orders' link if Filter WooCommerce Orders by Payment Method plugin is installed.
	 *
	 * @hooked plugin_action_links_{plugin basename}
	 *
	 * @param string[] $links_array The links that will be shown below the plugin name on plugins.php (usually "Deactivate").
	 *
	 * @return string[]
	 * @see \WP_Plugins_List_Table::display_rows()
	 */
	public function add_orders_action_link( array $links_array ): array {

		$plugin_links = array();

		/**
		 * Add an "Orders" link to a filtered list of orders if the Filter WooCommerce Orders by Payment Method plugin is installed.
		 *
		 * @see https://www.skyverge.com/blog/filtering-woocommerce-orders/
		 */
		if ( is_plugin_active( 'wc-filter-orders-by-payment/filter-wc-orders-by-gateway.php' ) && is_plugin_active( 'woocommerce/woocommerce.php' ) ) {

			$params = array(
				'post_type'                  => 'shop_order',
				'_shop_order_payment_method' => 'bh_bitcoin',
			);

			$orders_link    = add_query_arg( $params, admin_url( 'edit.php' ) );
			$plugin_links[] = '<a href="' . $orders_link . '">' . __( 'Orders', 'bh-wp-bitcoin-gateway' ) . '</a>';
		}

		return array_merge( $plugin_links, $links_array );
	}

	/**
	 * There are two authors in the plugin header but WordPress only allows one author link.
	 * This function just replaces the generated HTML with two links.
	 *
	 * @param array<int|string, string> $plugin_meta The meta information/links displayed by the plugin description.
	 * @param string                    $plugin_file_name The plugin filename to match when filtering.
	 *
	 * @return array<int|string, string>
	 */
	public function split_author_link_into_two_links( array $plugin_meta, string $plugin_file_name ): array {

		if ( $this->settings->get_plugin_basename() !== $plugin_file_name ) {

			return $plugin_meta;
		}

		$updated_plugin_meta = array();
		foreach ( $plugin_meta as $key => $entry ) {

			if ( str_contains( $entry, 'Nullcorps, BrianHenryIE' ) ) {
				$entry = 'By <a href="https://github.com/Nullcorps/">Nullcorps</a>, <a href="https://brianhenry.ie/">BrianHenryIE</a>';
			}

			$updated_plugin_meta[ $key ] = $entry;

		}

		return $updated_plugin_meta;
	}
}
