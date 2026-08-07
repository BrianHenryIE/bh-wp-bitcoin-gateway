<?php
/**
 * Bitcoin payment gateway for WordPress – WooCommerce.
 *
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
 * Author URI:             https://bhwp.ie
 * License:                GNU General Public License v3.0
 * License URI:            http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:            bh-wp-bitcoin-gateway
 * Domain Path:            /languages
 * WC requires at least:   10.1.2
 * WC tested up to:        10.1.2
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\API_Background_Jobs_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler;
use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\Wallet\Nimq_API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Blockchain_Info_Api;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Blockstream_Info_API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate\Bitfinex_API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\Generate_Address_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Settings;
use BrianHenryIE\WP_Bitcoin_Gateway\Art4\Requests\Psr\HttpClient;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\BlockchainInfoApi;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order\Woo_Cancel_Abandoned_Order_Integration;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\WooCommerce_Integration;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperInterface;
use BrianHenryIE\WP_Bitcoin_Gateway\League\Container\Container;
use BrianHenryIE\WP_Bitcoin_Gateway\League\Container\ReflectionContainer;
use BrianHenryIE\WP_Bitcoin_Gateway\WC_Logger\WC_Logger_Settings_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\WC_Logger\WC_PSR_Logger;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Activator;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Deactivator;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Logger\Logger_Settings_Interface;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
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
// Autowire unbound concrete classes by reflection. `false` (no caching) means a new
// instance per get() for anything not addShared().
$container->delegate( new ReflectionContainer( false ) );

// league/container does not self-bind; this is needed to autowire classes that constructor-inject the container.
$container->addShared( ContainerInterface::class, static fn(): ContainerInterface => $container );

$container->add( Background_Jobs_Scheduler_Interface::class, Background_Jobs_Scheduler::class );
$container->add( Background_Jobs_Actions_Interface::class, Background_Jobs_Actions_Handler::class );

$container->add( API_Background_Jobs_Interface::class, API::class );
$container->add( API_Interface::class, API::class );

$container->add( Settings_Interface::class, Settings::class );
$container->add( Logger_Settings_Interface::class, Settings::class );
// BH WP Logger doesn't add its own hooks unless we use its singleton.
// NB: league/container definition closures receive no arguments — capture $container with `use` if needed.
$container->addShared(
	LoggerInterface::class,
	static function (): LoggerInterface {
		return new WC_PSR_Logger(
			new class() implements WC_Logger_Settings_Interface {
				/**
				 * Get the plugin slug for logging.
				 */
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

$container->addShared(
	JsonMapperInterface::class,
	static function () use ( $container ): JsonMapperInterface {
		/** @var JsonMapper_Helper $json_mapper_helper */
		$json_mapper_helper = $container->get( JsonMapper_Helper::class );
		return $json_mapper_helper->build();
	}
);

// $container->add( RequestFactoryInterface::class, HttpClient::class );
// $container->add( ClientInterface::class, HttpClient::class );
// $container->add( Blockchain_API_Interface::class, Blockchain_Info_Api::class );
$container->add( Blockchain_API_Interface::class, Blockstream_Info_API::class );

$container->add( Generate_Address_API_Interface::class, Nimq_API::class );
$container->add( Exchange_Rate_API_Interface::class, Bitfinex_API::class );

// Registered here rather than in WooCommerce_Integration / BH_WP_Bitcoin_Gateway constructors so
// consumers only depend on ContainerInterface. Resolution is lazy.
$container->add( API_WooCommerce_Interface::class, API_WooCommerce::class );

/** @var BH_WP_Bitcoin_Gateway $app */
$app = $container->get( BH_WP_Bitcoin_Gateway::class );
$app->register_hooks();

$GLOBALS['bh_wp_bitcoin_gateway'] = $container->get( API_Interface::class );

/**
 * @hooked plugins_loaded
 */
$boot_integrations = function () use ( $container ): void {
	/** @var LoggerInterface $logger */
	$logger = $container->get( LoggerInterface::class );

	/** @var class-string[] $integrations */
	$integrations = apply_filters(
		// TODO: How to annotate this to generate documentation?
		'bh_wp_bitcoin_gateway_integrations',
		array(
			WooCommerce_Integration::class,
			// If this were to fail when being instantiated below, it would fail with a logged warning.
			Woo_Cancel_Abandoned_Order_Integration::class,
		)
	);

	foreach ( $integrations as $integration ) {
		try {
			/** @var object $instance */
			$instance = $container->get( $integration );
			if ( method_exists( $instance, 'register_hooks' ) ) {
				$instance->register_hooks();
			}
		} catch ( Exception $e ) {
			$logger->warning(
				'Error booting {integration} – {error_message}.',
				array(
					'integration'   => $integration,
					'error_message' => $e->getMessage(),
					'exception'     => $e,
				)
			);
		}
	}
};
add_action( 'plugins_loaded', $boot_integrations, 0 );
