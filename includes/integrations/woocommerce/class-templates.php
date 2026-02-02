<?php
/**
 * Register the `bitcoin-paid.php` and `bitcoin-unpaid.php` templates so they can be found with `wc_get_template()`.
 *
 * These templates are used on the Thank You page, Emails, and the My Account page to display payment instructions
 * and details.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;

/**
 * Hooks into the wc_get_template filter called inside `wc_get_template()` to return templates inside this plugin
 * if they have not already been provided by the theme or another plugin.
 */
class Templates {

	const BITCOIN_UNPAID_TEMPLATE_NAME = 'bitcoin-unpaid.php';
	const BITCOIN_PAID_TEMPLATE_NAME   = 'bitcoin-paid.php';

	/**
	 * Constructor.
	 *
	 * @param Settings_Interface $settings The plugin settings.
	 */
	public function __construct( protected Settings_Interface $settings ) {
	}

	/**
	 * Returns the full template path for templates defined within this plugin.
	 * If a template has already been specified on this filter, that is returned.
	 * If the template exists within the current theme, that is returned.
	 *
	 * `wc_get_template( 'bitcoin-paid.php', $formatted_order_details_array );`.
	 *
	 * @see woocommerce_locate_template
	 * @see https://wphave.com/include-woocommerce-templates-from-plugin/
	 *
	 * @hooked wc_get_template
	 *
	 * @param string       $template The full path to the template. Usually an incorrect (!file_exists()) path before this function runs.
	 * @param string       $template_name The template name, i.e. the relative filename from the theme or theme/woocommerce directory.
	 * @param array<mixed> $_args Array of values to be exploded and made available to the included template.
	 * @param string       $_template_path I'm not sure is there a difference between `$template` and `$template_path`.
	 * @param string       $_default_path Optional default path, which seems to be empty in WooCommerce core.
	 *
	 * @return string
	 */
	public function load_bitcoin_templates( string $template, string $template_name, array $_args, string $_template_path, string $_default_path ): string {

		$templates = array(
			self::BITCOIN_UNPAID_TEMPLATE_NAME,
			self::BITCOIN_PAID_TEMPLATE_NAME,
			Email::TEMPLATE_NAME,
			My_Account_View_Order::TEMPLATE_NAME,
			Thank_You::TEMPLATE_NAME,
			Admin_Order_UI::TEMPLATE_NAME,
		);

		// Unrelated to us, leave early.
		if ( ! in_array( $template_name, $templates, true ) ) {
			return $template;
		}

		// It will default to a string suggesting the template exists under the WooCommerce plugin directory, which will not exist.
		// Or could be already set by an earlier filter, where it probably will exist.
		if ( ! empty( $template ) && file_exists( $template ) ) {
			return $template;
		}

		// Check does it exist inside the theme.
		$theme_template = locate_template( array( '/woocommerce/' . $template_name, $template_name ) );

		if ( $theme_template ) {
			return $theme_template;
		}

		return $this->settings->get_plugin_dir() . 'templates/' . $template_name;
	}
}
