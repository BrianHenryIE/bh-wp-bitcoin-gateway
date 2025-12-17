<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * frontend-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Register_List_Tables;
use BrianHenryIE\WP_Bitcoin_Gateway\Frontend\Blocks\Bitcoin_Image_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order\Woo_Cancel_Abandoned_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Exchange_Rate_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Address_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Amount_Received_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Last_Checked_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Status_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Total_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Checkout;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\HPOS;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Order;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\Admin\Plugins_Page;
use BrianHenryIE\WP_Bitcoin_Gateway\Frontend\AJAX;
use BrianHenryIE\WP_Bitcoin_Gateway\Frontend\Frontend_Assets;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Admin_Order_UI;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Email;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\My_Account_View_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Bitcoin_Order_Confirmation_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Payment_Gateways;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Templates;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Thank_You;
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
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
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

		$this->define_frontend_hooks();
		$this->define_template_hooks();

		$this->define_payment_gateway_hooks();
		$this->define_woocommerce_checkout_hooks();
		$this->define_order_hooks();
		$this->define_action_scheduler_hooks();

		$this->define_thank_you_hooks();
		$this->define_email_hooks();
		$this->define_my_account_hooks();

		$this->define_admin_order_ui_hooks();
		$this->define_wp_list_page_ui_hooks();

		$this->define_woocommerce_features_hooks();

		$this->define_cli_commands();

		$this->define_integration_woo_cancel_abandoned_order_hooks();
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
	}

	/**
	 * Enqueue styles, scripts and AJAX to style and handle the templates.
	 *
	 * @since    1.0.0
	 */
	protected function define_frontend_hooks(): void {

		/** @var Frontend_Assets $plugin_frontend */
		$plugin_frontend = $this->container->get( Frontend_Assets::class );

		add_action( 'wp_enqueue_scripts', array( $plugin_frontend, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_frontend, 'enqueue_scripts' ) );

		/** @var AJAX $ajax */
		$ajax = $this->container->get( AJAX::class );

		add_action( 'wp_ajax_bh_wp_bitcoin_gateway_refresh_order_details', array( $ajax, 'get_order_details' ) );
		add_action( 'wp_ajax_nopriv_bh_wp_bitcoin_gateway_refresh_order_details', array( $ajax, 'get_order_details' ) );

		/** @var Bitcoin_Image_Block $bitcoin_image_block */
		$bitcoin_image_block = $this->container->get( Bitcoin_Image_Block::class );
		add_filter( 'get_block_type_variations', array( $bitcoin_image_block, 'add_bitcoin_image_variation' ), 10, 2 );
	}

	/**
	 * Hooks into WooCommerce templating system to provide the templates used to display the payment details
	 * after checkout, on the my-account order view, and in email.
	 */
	protected function define_template_hooks(): void {

		/** @var Templates $templates */
		$templates = $this->container->get( Templates::class );

		add_filter( 'wc_get_template', array( $templates, 'load_bitcoin_templates' ), 10, 5 );
	}

	/**
	 * Register the gateway class with WooCommerce.
	 * Add a filter for the WooCommerce Settings payment gateways view to filter to only Bitcoin gateways.
	 */
	protected function define_payment_gateway_hooks(): void {

		/** @var Payment_Gateways $payment_gateways */
		$payment_gateways = $this->container->get( Payment_Gateways::class );

		// Register the payment gateway with WooCommerce.
		add_filter( 'woocommerce_payment_gateways', array( $payment_gateways, 'add_to_woocommerce' ) );

		// Register the payment gateway with WooCommerce Blocks checkout.
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $payment_gateways, 'register_woocommerce_block_checkout_support' ) );

		add_filter( 'woocommerce_available_payment_gateways', array( $payment_gateways, 'add_logger_to_gateways' ) );
	}

	/**
	 * Always check for an unused address when opening the checkout.
	 */
	protected function define_woocommerce_checkout_hooks(): void {

		/** @var Checkout $order */
		$checkout = $this->container->get( Checkout::class );

		add_action( 'woocommerce_before_checkout_form', array( $checkout, 'generate_one_address_for_payment' ) );
	}

	/**
	 * Handle order status changes.
	 */
	protected function define_order_hooks(): void {

		/** @var Order $order */
		// $order = $this->container->get( Order::class );
		//
		// add_action( 'woocommerce_order_status_changed', array( $order, 'schedule_check_for_transactions' ), 10, 3 );
	}

	/**
	 * Hook into the Thank You page to display payment instructions / status.
	 */
	protected function define_thank_you_hooks(): void {

		/** @var Thank_You $thank_you */
		$thank_you = $this->container->get( Thank_You::class );
		add_action( 'woocommerce_thankyou', array( $thank_you, 'print_instructions' ), 5 );

		/** @var Bitcoin_Exchange_Rate_Block $bitcoin_exchange_rate_block */
		$bitcoin_exchange_rate_block = $this->container->get( Bitcoin_Exchange_Rate_Block::class );
		add_action( 'init', array( $bitcoin_exchange_rate_block, 'register_block' ) );

		/** @var Bitcoin_Order_Confirmation_Block $bitcoin_order_confirmation_block */
		$bitcoin_order_confirmation_block = $this->container->get( Bitcoin_Order_Confirmation_Block::class );
		add_action( 'init', array( $bitcoin_order_confirmation_block, 'register_block' ) );

		/** @var Bitcoin_Order_Payment_Status_Block $bitcoin_payment_status_block */
		$bitcoin_payment_status_block = $this->container->get( Bitcoin_Order_Payment_Status_Block::class );
		add_action( 'init', array( $bitcoin_payment_status_block, 'register_block' ) );

		/** @var Bitcoin_Order_Payment_Address_Block $bitcoin_payment_address_block */
		$bitcoin_payment_address_block = $this->container->get( Bitcoin_Order_Payment_Address_Block::class );
		add_action( 'init', array( $bitcoin_payment_address_block, 'register_block' ) );

		/** @var Bitcoin_Order_Payment_Total_Block $bitcoin_payment_total_block */
		$bitcoin_payment_total_block = $this->container->get( Bitcoin_Order_Payment_Total_Block::class );
		add_action( 'init', array( $bitcoin_payment_total_block, 'register_block' ) );

		/** @var Bitcoin_Order_Payment_Amount_Received_Block $bitcoin_payment_amount_received_block */
		$bitcoin_payment_amount_received_block = $this->container->get( Bitcoin_Order_Payment_Amount_Received_Block::class );
		add_action( 'init', array( $bitcoin_payment_amount_received_block, 'register_block' ) );

		/** @var Bitcoin_Order_Payment_Last_Checked_Block $bitcoin_payment_last_checked_block */
		$bitcoin_payment_last_checked_block = $this->container->get( Bitcoin_Order_Payment_Last_Checked_Block::class );
		add_action( 'init', array( $bitcoin_payment_last_checked_block, 'register_block' ) );
	}

	/**
	 * Hook into emails and send payment instructions / status for related orders.
	 */
	protected function define_email_hooks(): void {

		/** @var Email $email */
		$email = $this->container->get( Email::class );

		// TODO: Before table? best place?
		add_action( 'woocommerce_email_before_order_table', array( $email, 'print_instructions' ), 10, 3 );
	}

	/**
	 * Add hooks to display the Bitcoin payment details on the single order view in my-account.
	 */
	protected function define_my_account_hooks(): void {

		/** @var My_Account_View_Order $my_account_order */
		$my_account_order = $this->container->get( My_Account_View_Order::class );

		add_action( 'woocommerce_view_order', array( $my_account_order, 'print_status_instructions' ), 9 );
	}

	/**
	 * Handle Action Scheduler invoked actions to generate new addresses and check unpaid orders.
	 */
	protected function define_action_scheduler_hooks(): void {

		/** @var Background_Jobs_Actions_Interface $background_jobs_actions_handler */
		$background_jobs_actions_handler = $this->container->get( Background_Jobs_Actions_Interface::class );

		add_action( Background_Jobs_Actions_Interface::GENERATE_NEW_ADDRESSES_HOOK, array( $background_jobs_actions_handler, 'generate_new_addresses' ) );
		add_action( Background_Jobs_Actions_Interface::CHECK_NEW_ADDRESSES_TRANSACTIONS_HOOK, array( $background_jobs_actions_handler, 'check_new_addresses_for_transactions' ) );
		add_action( Background_Jobs_Actions_Interface::CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK, array( $background_jobs_actions_handler, 'check_assigned_addresses_for_transactions' ) );
		add_action( Background_Jobs_Actions_Interface::CHECK_FOR_ASSIGNED_ADDRESSES_HOOK, array( $background_jobs_actions_handler, 'schedule_check_for_assigned_addresses_repeating_action' ) );
		add_action( 'action_scheduler_run_recurring_actions_schedule_hook', array( $background_jobs_actions_handler, 'ensure_schedule_repeating_actions' ) );
	}

	/**
	 * Add a meta box to the admin order view showing the Bitcoin total, address and transactions.
	 */
	protected function define_admin_order_ui_hooks(): void {

		/** @var Admin_Order_UI $admin_order_ui */
		$admin_order_ui = $this->container->get( Admin_Order_UI::class );

		add_action( 'add_meta_boxes', array( $admin_order_ui, 'register_address_transactions_meta_box' ) );
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
	 * Declare compatibility with WooCommerce High Performance Order Storage.
	 */
	protected function define_woocommerce_features_hooks(): void {

		/** @var HPOS $hpos */
		$hpos = $this->container->get( HPOS::class );

		add_action( 'before_woocommerce_init', array( $hpos, 'declare_compatibility' ) );
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

	/**
	 * Add filters to enable support for WooCommerce Cancel Abandoned Order plugin.
	 *
	 * @see https://wordpress.org/plugins/woo-cancel-abandoned-order/
	 */
	protected function define_integration_woo_cancel_abandoned_order_hooks(): void {

		/** @var Woo_Cancel_Abandoned_Order $woo_cancel_abandoned_order */
		$woo_cancel_abandoned_order = $this->container->get( Woo_Cancel_Abandoned_Order::class );

		add_filter( 'woo_cao_gateways', array( $woo_cancel_abandoned_order, 'enable_cao_for_bitcoin' ) );
		add_filter( 'woo_cao_before_cancel_order', array( $woo_cancel_abandoned_order, 'abort_canceling_partially_paid_order' ), 10, 3 );
	}
}
