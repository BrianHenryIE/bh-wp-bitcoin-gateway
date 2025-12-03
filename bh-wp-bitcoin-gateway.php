<?php
/**
 * This Bitcoin gateway relies on the BitWasp bitwasp/bitcoin-php PHP library for the maths/heavy lifting.
 *
 * @see https://github.com/Bit-Wasp/bitcoin-php
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           brianhenryie/bh-wp-bitcoin-gateway
 *
 * @wordpress-plugin
 * Plugin Name:            Bitcoin Gateway
 * Plugin URI:             http://github.com/BrianHenryIE/bh-wp-bitcoin-gateway/
 * Description:            Accept Bitcoin payments using self-custodied wallets, and no external account. Calculates wallet addresses locally and uses open APIs to verify payments. For an emphasis on privacy & sovereignty.
 * Version:                2.0.0-beta-8
 * Requires at least:      5.9
 * Requires PHP:           8.4
 * Author:                 Nullcorps, BrianHenryIE
 * Author URI:             https://github.com/Nullcorps/
 * License:                GNU General Public License v3.0
 * License URI:            http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:            bh-wp-bitcoin-gateway
 * Domain Path:            /languages
 * WC requires at least:   10.1.2
 * WC tested up to:        10.1.2
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\API_Background_Jobs_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduling_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Nimq_API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain\Blockstream_Info_API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate\Bitfinex_API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Settings;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\Container;
use BrianHenryIE\WP_Bitcoin_Gateway\WC_Logger\WC_Logger_Settings_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\WC_Logger\WC_PSR_Logger;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Activator;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Deactivator;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\Logger;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\Logger_Settings_Interface;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	throw new Exception( 'WPINC not defined' );
}

// If the GitHub repo was installed without running `composer install` to add the dependencies, the autoload will fail.
try {
	require_once plugin_dir_path( __FILE__ ) . 'autoload.php';
} catch ( Throwable $error ) {
	$display_download_from_releases_error_notice = function () {
		echo '<div class="notice notice-error"><p><b>Bitcoin Gateway missing dependencies.</b> Please <a href="https://github.com/BrianHenryIE/bh-wp-bitcoin-gateway/releases">install the distribution archive from the GitHub Releases page</a>. It appears you downloaded the GitHub repo and installed that as the plugin.</p></div>';
	};
	add_action( 'admin_notices', $display_download_from_releases_error_notice );
	return;
}

/**
 * Current plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BH_WP_BITCOIN_GATEWAY_VERSION', '2.0.0' );

define( 'BH_WP_BITCOIN_GATEWAY_BASENAME', plugin_basename( __FILE__ ) );
define( 'BH_WP_BITCOIN_GATEWAY_PATH', trailingslashit( __DIR__ ) );
define( 'BH_WP_BITCOIN_GATEWAY_URL', trailingslashit( plugins_url( plugin_basename( __DIR__ ) ) ) );

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

$container = new Container();

$container->singleton(
	Background_Jobs::class,
	static function ( Container $container ) {
		return $container->make( Background_Jobs::class );
	}
);

$container->bind( Background_Jobs_Scheduling_Interface::class, Background_Jobs::class );
$container->bind( Background_Jobs_Actions_Interface::class, Background_Jobs::class );

$container->singleton(
	API::class,
	static function ( Container $container ) {
		$api             = $container->make( API::class );
		$background_jobs = $container->get( Background_Jobs::class );
		$api->set_background_jobs( $background_jobs );
		return $api;
	}
);

$container->bind( API_Background_Jobs_Interface::class, API::class );
$container->bind( API_WooCommerce_Interface::class, API::class );

$container->bind( API_Interface::class, API::class );
$container->bind( Settings_Interface::class, Settings::class );
$container->bind( LoggerInterface::class, Logger::class );
$container->bind( Logger_Settings_Interface::class, Settings::class );
// BH WP Logger doesn't add its own hooks unless we use its singleton.
$container->singleton(
	LoggerInterface::class,
	static function ( Container $container ) {
		return new WC_PSR_Logger(
			new class() implements WC_Logger_Settings_Interface {
				public function get_plugin_slug(): string {
					return 'bh-wp-bitcoin-gateway';
				}

				/**
				 * Record all logs from this level and above.
				 */
				public function get_log_level(): string {
					return LogLevel::DEBUG;
				}
			}
		);
	}
);

$container->bind( Blockchain_API_Interface::class, Blockstream_Info_API::class );
$container->bind( Generate_Address_API_Interface::class, Nimq_API::class );
$container->bind( Exchange_Rate_API_Interface::class, Bitfinex_API::class );

$app = $container->get( BH_WP_Bitcoin_Gateway::class );
$app->register_hooks();

$GLOBALS['bh_wp_bitcoin_gateway'] = $container->get( API_Interface::class );
