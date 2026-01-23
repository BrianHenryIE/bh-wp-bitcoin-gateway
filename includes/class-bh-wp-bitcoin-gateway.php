<?php
/**
 * The file that registers the hooks for the plugin.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Register_List_Tables;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Plugins_Page;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\CLI;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\I18n;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WP_CLI;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * frontend-facing site hooks.
 */
class BH_WP_Bitcoin_Gateway {

	/**
	 * @param ContainerInterface $container The DI container.
	 */
	public function __construct(
		protected ContainerInterface $container
	) {
	}

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the frontend-facing side of the site.
	 */
	public function register_hooks(): void {
		$this->set_locale();

		$this->define_plugins_page_hooks();

		$this->define_custom_post_type_hooks();

		$this->define_action_scheduler_hooks();

		$this->define_wp_list_page_ui_hooks();

		$this->define_cli_commands();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 */
	protected function set_locale(): void {

		/** @var I18n $plugin_i18n */
		$plugin_i18n = $this->container->get( I18n::class );

		add_action( 'init', array( $plugin_i18n, 'load_plugin_textdomain' ) );
	}

	/**
	 * Hooks to add a "Settings" link on plugins.php.
	 * And a link to an orders filter (where possible).
	 */
	protected function define_plugins_page_hooks(): void {

		/** @var Plugins_Page $plugins_page */
		$plugins_page = $this->container->get( Plugins_Page::class );

		/** @var Settings_Interface $settings */
		$settings        = $this->container->get( Settings_Interface::class );
		$plugin_basename = $settings->get_plugin_basename();

		add_filter( "plugin_action_links_{$plugin_basename}", array( $plugins_page, 'add_settings_action_link' ) );
		add_filter( "plugin_action_links_{$plugin_basename}", array( $plugins_page, 'add_orders_action_link' ) );

		add_filter( 'plugin_row_meta', array( $plugins_page, 'split_author_link_into_two_links' ), 10, 2 );
	}

	/**
	 * Add hooks for defining post types for the wallets and destination addresses.
	 */
	protected function define_custom_post_type_hooks(): void {

		/** @var Post_BH_Bitcoin_Wallet $wallet */
		$wallet = $this->container->get( Post_BH_Bitcoin_Wallet::class );
		add_action( 'init', array( $wallet, 'register_wallet_post_type' ) );

		/** @var Post_BH_Bitcoin_Address $address */
		$address = $this->container->get( Post_BH_Bitcoin_Address::class );
		add_action( 'init', array( $address, 'register_address_post_type' ) );
		add_action( 'parse_query', array( $address, 'add_post_statuses' ) );

		/** @var Post_BH_Bitcoin_Transaction $transaction_post_type */
		$transaction_post_type = $this->container->get( Post_BH_Bitcoin_Transaction::class );
		add_action( 'init', array( $transaction_post_type, 'register_transaction_post_type' ) );
	}

	/**
	 * Handle Action Scheduler invoked actions to generate new addresses and check unpaid orders.
	 */
	protected function define_action_scheduler_hooks(): void {

		/** @var Background_Jobs_Actions_Interface $background_jobs_actions_handler */
		$background_jobs_actions_handler = $this->container->get( Background_Jobs_Actions_Interface::class );

		add_action( 'action_scheduler_run_recurring_actions_schedule_hook', array( $background_jobs_actions_handler, 'add_action_scheduler_repeating_actions' ) );

		add_action( Background_Jobs_Actions_Interface::UPDATE_EXCHANGE_RATE_HOOK, array( $background_jobs_actions_handler, 'update_exchange_rate' ) );

		add_action( Background_Jobs_Actions_Interface::RECURRING_ENSURE_UNUSED_ADDRESSES_HOOK, array( $background_jobs_actions_handler, 'ensure_unused_addresses' ) );
		add_action( Background_Jobs_Actions_Interface::SINGLE_ENSURE_UNUSED_ADDRESSES_HOOK, array( $background_jobs_actions_handler, 'single_ensure_unused_addresses' ) );

		add_action( Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK, array( $background_jobs_actions_handler, 'generate_new_addresses' ) );

		add_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK, array( $background_jobs_actions_handler, 'check_new_addresses_for_transactions' ) );
		add_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK, array( $background_jobs_actions_handler, 'check_assigned_addresses_for_transactions' ) );
	}

	/**
	 * Customize the columns and data shown in the WP_List_Table for bitcoin wallets and bitcoin addresses.
	 */
	protected function define_wp_list_page_ui_hooks(): void {

		/** @var Register_List_Tables $register_list_tables */
		$register_list_tables = $this->container->get( Register_List_Tables::class );

		add_filter( 'wp_list_table_class_name', array( $register_list_tables, 'register_bitcoin_address_table' ), 10, 2 );
		add_filter( 'wp_list_table_class_name', array( $register_list_tables, 'register_bitcoin_wallet_table' ), 10, 2 );
	}

	/**
	 * Register WP CLI commands.
	 *
	 * `wp bh-bitcoin generate-new-addresses`
	 */
	protected function define_cli_commands(): void {

		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}

		/** @var CLI $cli */
		$cli = $this->container->get( CLI::class );

		try {
			WP_CLI::add_command( 'bh-bitcoin generate-new-addresses', array( $cli, 'generate_new_addresses' ) );
			WP_CLI::add_command( 'bh-bitcoin check-transactions', array( $cli, 'check_transactions' ) );
		} catch ( Exception $e ) {
			/** @var LoggerInterface $logger */
			$logger = $this->container->get( LoggerInterface::class );
			$logger->error( 'Failed to register WP CLI commands: ' . $e->getMessage(), array( 'exception' => $e ) );
		}
	}
}
