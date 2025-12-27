<?php
/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {

	/**
	 * TODO: (related)
	 * If WooCommerce is deactivated for a day, and during that time this plugin see a payment and marks
	 * the Bitcoin_Address as paid. We need a hook somewhere (Woo's activation hook) to run an action to
	 * sync back up. ~~ List all WC_Order whose gateway is Bitcoin, loop over them and read the Bitcoin_Address object.
	 */
	public static function activate(): void {
	}
}
