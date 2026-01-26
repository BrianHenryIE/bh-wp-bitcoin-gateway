<?php
/**
 * WooCommerce specific functionality.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\Frontend\Blocks\Bitcoin_Image_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Bitcoin_Order_Confirmation_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Exchange_Rate_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Address_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Amount_Received_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Last_Checked_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Status_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Order_Confirmation\Bitcoin_Order_Payment_Total_Block;
use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\Container as DI52_Container;
use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\ContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Instantiate classes and register hooks with WordPress.
 */
class WooCommerce_Integration {

	/**
	 * Constructor
	 *
	 * @param ContainerInterface&DI52_Container $container PSR container.
	 * @throws ContainerException E.g. if the bound class cannot be instantiated.
	 */
	public function __construct(
		protected DI52_Container $container,
	) {
		$this->container->bind( API_WooCommerce_Interface::class, API_WooCommerce::class );
	}

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the frontend-facing side of the site.
	 *
	 * @throws NotFoundExceptionInterface If the class cannot be resolved (often when an interface does not have a bound class).
	 * @throws ContainerException Other problems when instantiating the requested class.
	 * @throws ContainerExceptionInterface PSR interface for all container exceptions.
	 */
	public function register_hooks(): void {

		$this->define_frontend_hooks();
		$this->define_template_hooks();

		$this->define_payment_gateway_hooks();
		$this->define_woocommerce_checkout_hooks();

		$this->define_thank_you_hooks();
		$this->define_email_hooks();
		$this->define_my_account_hooks();

		$this->define_admin_order_ui_hooks();

		$this->define_woocommerce_features_hooks();

		$this->define_address_list_table_hooks();
	}

	/**
	 * Enqueue styles, scripts and AJAX to style and handle the templates.
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

		/** @var Menu $menu */
		$menu = $this->container->get( Menu::class );

		add_action( 'admin_menu', array( $menu, 'add_woocommerce_payments_submenu' ) );
	}

	/**
	 * Always check for an unused address when opening the checkout.
	 */
	protected function define_woocommerce_checkout_hooks(): void {

		/** @var Checkout $checkout */
		$checkout = $this->container->get( Checkout::class );

		add_action( 'woocommerce_checkout_init', array( $checkout, 'ensure_one_address_for_payment' ) );
	}

	/**
	 * Hook into the "Thank You" page to display payment instructions / status.
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
	 * Add a meta box to the admin order view showing the Bitcoin total, address and transactions.
	 */
	protected function define_admin_order_ui_hooks(): void {

		/** @var Admin_Order_UI $admin_order_ui */
		$admin_order_ui = $this->container->get( Admin_Order_UI::class );

		add_action( 'add_meta_boxes', array( $admin_order_ui, 'register_address_transactions_meta_box' ) );
	}

	/**
	 * Declare compatibility with WooCommerce High Performance Order Storage.
	 *
	 * @see wp-admin/plugins.php?plugin_status=incompatible_with_feature
	 */
	protected function define_woocommerce_features_hooks(): void {

		/** @var HPOS $hpos */
		$hpos = $this->container->get( HPOS::class );

		add_action( 'before_woocommerce_init', array( $hpos, 'declare_compatibility' ) );
	}

	/**
	 * Filter the output of `wp-admin/edit.php?post_type=bh-bitcoin-address` to include links to WooCommerce gateways.
	 */
	protected function define_address_list_table_hooks(): void {

		/** @var Addresses_List_Table $address_list_table */
		$address_list_table = $this->container->get( Addresses_List_Table::class );

		add_filter(
			'bh_wp_bitcoin_gateway_gateway_link',
			array(
				$address_list_table,
				'woocommerce_gateway_link',
			),
			10,
			5
		);
	}
}
