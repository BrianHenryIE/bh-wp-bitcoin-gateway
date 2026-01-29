<?php
/**
 * The main payment gateway class for the plugin.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\API;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Helpers\WC_Order_Meta_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use WC_Order;
use WC_Payment_Gateway;
use WC_Product;

/**
 * Simple instance of WC_Payment Gateway. Defines the admin settings and processes the payment.
 *
 * @see WC_Settings_API
 */
class Bitcoin_Gateway extends WC_Payment_Gateway {
	use LoggerAwareTrait;

	/**
	 * The default id for an instance of this gateway (typically there will only be one).
	 *
	 * @override WC_Settings_API::$id
	 *
	 * @var string
	 */
	public $id = 'bh_bitcoin';

	/**
	 * A cache so {@see Bitcoin_Gateway::is_available()} only runs once.
	 */
	protected ?bool $is_available_cache = null;

	/**
	 * Constructor for the gateway.
	 *
	 * Used to generate new wallets when the xpub is entered, and to fetch addresses when orders are placed.
	 *
	 * @param API_Interface             $api The main plugin functions.
	 * @param API_WooCommerce_Interface $api_woocommerce The WooCommerce specific functions.
	 * @param Settings_Interface        $plugin_settings Used to read the gateway settings from wp_options before they are initialized in this class.
	 * @param LoggerInterface           $logger PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected API_WooCommerce_Interface $api_woocommerce,
		protected Settings_Interface $plugin_settings,
		LoggerInterface $logger,
	) {
		/**
		 * Set a null logger to prevent null pointer exceptions. Later this will be correctly set
		 * with the plugin's real logger.
		 *
		 * @see Payment_Gateways::add_logger_to_gateways()
		 */
		$this->setLogger( $logger );

		$this->icon               = plugins_url( 'assets/bitcoin.png', 'bh-wp-bitcoin-gateway/bh-wp-bitcoin-gateway.php' );
		$this->has_fields         = false;
		$this->method_title       = __( 'Bitcoin', 'bh-wp-bitcoin-gateway' );
		$this->method_description = __( 'Accept Bitcoin payments. Customers are shown payment instructions and a QR code. Orders are marked paid once payment is confirmed on the blockchain.', 'bh-wp-bitcoin-gateway' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		/**
		 * This class extends WC_Payment_Gateway which extends WC_Settings_API. Each instance needs
		 *
		 * @see WC_Settings_API::process_admin_options()
		 */
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		add_action( 'admin_notices', array( $this, 'display_errors' ), 9999 );
	}

	/**
	 * Return the description for admin screens.
	 *
	 * @see parent::get_method_description()
	 *
	 * @return string
	 */
	public function get_method_description() {
		$method_description = $this->method_description . PHP_EOL;

		$method_description .= PHP_EOL;
		$method_description .= PHP_EOL;
		$method_description .= $this->get_formatted_exchange_rate_string();
		$method_description .= ' • ';
		$method_description .= $this->get_view_scheduled_actions_link();

		if ( $this->is_site_using_full_site_editing() ) {
			$method_description .= PHP_EOL;
			$method_description .= PHP_EOL;
			$method_description .= $this->get_formatted_link_to_order_confirmation_edit();
		}

		$filtered = apply_filters( 'woocommerce_gateway_method_description', $method_description, $this );

		return is_string( $filtered ) ? $filtered : $method_description;
	}

	/**
	 * Build a link to Action Scheduler's view, filtered to this plugin's jobs.
	 */
	protected function get_view_scheduled_actions_link(): string {
		return sprintf(
			'<a href="%s">View Scheduled Actions</a>',
			add_query_arg(
				array(
					'page'    => 'action-scheduler',
					'status'  => 'pending',
					'orderby' => 'schedule',
					'order'   => 'desc',
					's'       => 'bh_wp_bitcoin_gateway',
				),
				admin_url(
					'tools.php'
				)
			)
		);
	}

	/**
	 * Returns the exchange rate in a string, e.g. 'Current exchange rate: 1 BTC = $100,000'.
	 *
	 * @throws UnknownCurrencyException If the store currency is not an ISO 4217 brick/money supported currency code.
	 */
	protected function get_formatted_exchange_rate_string(): string {
		try {
			$currency = Currency::of( get_woocommerce_currency() );
		} catch ( UnknownCurrencyException $e ) {
			$currency = Currency::of( 'USD' );
		}
		$exchange_rate = $this->api->get_exchange_rate( $currency );
		if ( is_null( $exchange_rate ) ) {
			// TODO: Also display an admin notice with instruction to configure / retry.
			return 'Error fetching exchange rate. Gateway will be unavailable to customers until an exchange rate is available.';
		}
		return sprintf(
			'Current exchange rate: 1 BTC = %s',
			wc_price(
				$exchange_rate->getAmount()->toFloat(),
				array(
					'currency' => $exchange_rate->getCurrency()->getCurrencyCode(),
				)
			),
		);
	}

	/**
	 * Determine if the site is using a full site editing theme.
	 */
	protected function is_site_using_full_site_editing(): bool {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Get an anchor link for the full site editing page for the order confirmation template.
	 */
	protected function get_formatted_link_to_order_confirmation_edit(): string {
		return sprintf(
			'<a href="%s" target="_blank">Edit the order confirmation page</a>.',
			add_query_arg(
				array(
					'postType' => 'wp_template',
					'postId'   => 'woocommerce/woocommerce//order-confirmation',
					'canvas'   => 'edit',
				),
				admin_url( 'site-editor.php' )
			)
		);
	}

	/**
	 * When saving the options, if the xpub is changed, initiate a background job to generate addresses.
	 *
	 * @see \WC_Settings_API::process_admin_options()
	 *
	 * @return bool
	 */
	public function process_admin_options() {

		$xpub_before = $this->get_xpub();

		// This gets the `$_POST` data and saves it.
		$options_updated = parent::process_admin_options();

		// Regardless whether the wallet address has changed, ensure it exists.

		$xpub_after = $this->get_xpub();

		if ( ! is_null( $xpub_after ) ) {
			$this->api->get_or_save_wallet_for_master_public_key(
				$xpub_after,
				array(
					'integration' => WooCommerce_Integration::class,
					'gateway_id'  => $this->id,
				)
			);
		}

		// If nothing changed, we can return early.
		if ( ! $options_updated ) {
			return false;
		}

		// Other settings may have changed.
		if ( $xpub_after === $xpub_before ) {
			// Definitely no change!
			return $options_updated;
		}

		if ( is_null( $xpub_after ) ) {
			// The setting value was deleted.
			// TODO: maybe mark the wallet inactive.
			return $options_updated;
		}

		$this->logger->info(
			'New xpub key set for gateway {gateway_name}: {xpub_after}',
			array(
				'gateway_id'   => $this->id,
				'gateway_name' => $this->get_method_title(),
				'xpub_before'  => $xpub_before,
				'xpub_after'   => $xpub_after,
			)
		);

		// TODO: maybe mark the previous xpub's wallet as "inactive". (although it could be in use in another instance of the gateway).

		return $options_updated;
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 *
	 * @see WC_Settings_API::init_form_fields()
	 *
	 * @return void
	 */
	public function init_form_fields() {

		$settings_fields = array(

			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'bh-wp-bitcoin-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Bitcoin Payment', 'bh-wp-bitcoin-gateway' ),
				'default' => 'yes',
			),

			'title'        => array(
				'title'       => __( 'Title', 'bh-wp-bitcoin-gateway' ),
				'type'        => 'text',
				'description' => __( 'The payment method title the customer sees during checkout.', 'bh-wp-bitcoin-gateway' ),
				'default'     => __( 'Bitcoin', 'bh-wp-bitcoin-gateway' ),
				'desc_tip'    => false,
			),

			'description'  => array(
				'title'       => __( 'Description', 'bh-wp-bitcoin-gateway' ),
				'type'        => 'text',
				'description' => __( 'Text the customer will see when the gateway is chosen at checkout.', 'bh-wp-bitcoin-gateway' ),
				'default'     => __( 'Pay quickly and easily with Bitcoin', 'bh-wp-bitcoin-gateway' ),
				'desc_tip'    => false,
			),

			'xpub'         => array(
				'title'       => __( 'Master Public Key', 'bh-wp-bitcoin-gateway' ),
				'type'        => 'text',
				'description' => __( 'The xpub/ypub/zpub for your Bitcoin wallet, which we use to locally generate the addresses to pay to (no API calls). Find it in Electrum under menu:wallet/information. It looks like <code>xpub1a2bc3d4longalphanumericstring</code>', 'bh-wp-bitcoin-gateway' ),
				'default'     => '',
				'desc_tip'    => false,
			),

			'price_margin' => array(
				'title'             => __( 'price-margin', 'bh-wp-bitcoin-gateway' ),
				'type'              => 'number',
				'description'       => __( 'A percentage shortfall from the shown price which will be accepted, to allow for volatility.', 'bh-wp-bitcoin-gateway' ),
				'default'           => '2',
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 100,
					'step' => 1,
				),
				'desc_tip'          => false,
			),
		);

		/**
		 * Let's get some products, filter to one that can be purchased, then use it to link to the checkout so
		 * the admin can see what it will all look like.
		 *
		 * @var WC_Product[] $products
		 */
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 10,
			)
		);
		$products = array_filter(
			$products,
			function ( WC_Product $product ): bool {
				return $product->is_purchasable();
			}
		);
		if ( ! empty( $products ) ) {
			$a_product = array_pop( $products );

			$checkout_url                                   = add_query_arg(
				array(
					'add-to-cart'     => $a_product->get_id(),
					'payment_gateway' => $this->id,
				),
				wc_get_checkout_url()
			);
			$settings_fields['description']['description'] .= ' <a href="' . esc_url( $checkout_url ) . '" title="Adds an item to your cart and opens the checkout in a new tab.">Visit checkout</a>.';
		}

		$saved_xpub = $this->get_option( 'xpub' );
		if ( ! empty( $saved_xpub ) ) {
			$settings_fields['xpub']['description'] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=bh-bitcoin-address' ) ) . '">View addresses</a>';
		}

		$settings_fields['price_margin']['description'] .= __( 'See: ', 'bh-wp-bitcoin-gateway' ) . '<a href="https://buybitcoinworldwide.com/volatility-index/" target="_blank">Bitcoin Volatility</a>.';

		$log_levels        = array( 'none', LogLevel::ERROR, LogLevel::WARNING, LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG );
		$log_levels_option = array();
		foreach ( $log_levels as $log_level ) {
			$log_levels_option[ $log_level ] = ucfirst( $log_level );
		}

		$settings_fields['log_level'] = array(
			'title'       => __( 'Log Level', 'text-domain' ),
			'label'       => __( 'Enable Logging', 'text-domain' ),
			'type'        => 'select',
			'options'     => $log_levels_option,
			'description' => __( 'Increasingly detailed levels of logs. ', 'bh-wp-bitcoin-gateway' ) . '<a href="' . admin_url( 'admin.php?page=bh-wp-bitcoin-gateway-logs' ) . '">View Logs</a>',
			'desc_tip'    => false,
			'default'     => 'info',
		);

		$this->form_fields = (array) apply_filters( 'wc_gateway_bitcoin_form_fields', $settings_fields, $this->id );
	}


	/**
	 * Returns false when the gateway is not configured / has no addresses to use / has no exchange rate available.
	 *
	 * @see WC_Payment_Gateways::get_available_payment_gateways()
	 * @overrides {@see WC_Payment_Gateway::is_available()}
	 *
	 * @return bool
	 */
	public function is_available() {

		// Without the cache, when only one address was available, and an order was placed, we reached a point
		// where no addresses were available, so the placing the order would fail in the UI. In the backend the
		// order exists and the payment address is assigned.
		// By caching it for 15 seconds, we should be ok.

		// TODO: always keep more than two addresses available.

		$is_available_cache_key = 'bh-wp-bitcoin-gateway-available:' . __CLASS__ . $this->id;

		$is_available_cache_string = get_transient( $is_available_cache_key );
		if ( is_string( $is_available_cache_string ) ) {
			/** @var mixed|array{is_available:bool} $is_available_cache */
			$is_available_cache = json_decode( $is_available_cache_string, true );
			if ( is_array( $is_available_cache )
				&& isset( $is_available_cache['is_available'] )
				&& is_bool( $is_available_cache['is_available'] )
			) {
				return $is_available_cache['is_available'];
			}
		}

		if ( is_bool( $this->is_available_cache ) ) {
			return $this->is_available_cache;
		}

		if ( ! $this->api_woocommerce->is_unused_address_available_for_gateway( $this ) ) {
			$this->is_available_cache = false;
		} elseif ( is_null( $this->api->get_exchange_rate( Currency::of( get_woocommerce_currency() ) ) ) ) {
			$this->is_available_cache = false;
		} else {
			$this->is_available_cache = parent::is_available();
		}

		set_transient(
			$is_available_cache_key,
			wp_json_encode( array( 'is_available' => $this->is_available_cache ) ),
			15
		);

		return $this->is_available_cache;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id The id of the order being paid.
	 *
	 * @see WC_Payment_Gateway::process_payment()
	 *
	 * @return array{result:string, redirect:string}
	 * @throws BH_WP_Bitcoin_Gateway_Exception Throws an exception when no address is available (which is caught by WooCommerce and displayed at checkout).
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			// This should never happen.
			throw new BH_WP_Bitcoin_Gateway_Exception( __( 'Error creating order.', 'bh-wp-bitcoin-gateway' ) );
		}

		$fiat_total = Money::of( $order->get_total(), $order->get_currency() );

		$btc_total = $this->api->convert_fiat_to_btc( $fiat_total );

		/**
		 * There should never really be an exception here, since the availability of a fresh address was checked before
		 * offering the option to pay by Bitcoin.
		 *
		 * @see Bitcoin_Gateway::is_available()
		 */
		try {
			/**
			 *
			 * @see WC_Order_Meta_Helper::BITCOIN_ADDRESS_META_KEY
			 * @see Bitcoin_Address::get_raw_address()
			 */
			$btc_address = $this->api_woocommerce->assign_unused_address_to_order( $order, $btc_total );
		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage(), array( 'exception' => $e ) );
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Unable to find Bitcoin address to send to. Please choose another payment method.' );
		}

		$order_meta_helper = new WC_Order_Meta_Helper( new JsonMapper_Helper()->build() );

		/**
		 * Record the exchange rate at the time the order was placed.
		 *
		 * Although we're allowing for `::get_exchange_rate()` = `null` here, that should never happen since it was
		 * checked before the gateway was offered as a payment option.
		 */
		$exchange_rate = $this->api->get_exchange_rate( Currency::of( $order->get_currency() ) );
		if ( $exchange_rate ) {
			$order_meta_helper->set_exchange_rate( wc_order: $order, exchange_rate:$exchange_rate, save_now: false );
		}

		// TODO: the `save_now` here might better be `false` depending on how `update_status` works.
		$order_meta_helper->set_btc_total_price( wc_order: $order, btc_total:$btc_total, save_now: true );

		$btc_total_display = $btc_total->getAmount()->toFloat();

		// Mark as on-hold (we're awaiting the payment).
		/* translators: %F: The order total in BTC */
		$order->update_status( 'on-hold', sprintf( __( 'Awaiting Bitcoin payment of %F to address: ', 'bh-wp-bitcoin-gateway' ), $btc_total_display ) . '<a target="_blank" href="https://www.blockchain.com/btc/address/' . $btc_address->get_raw_address() . "\">{$btc_address->get_raw_address()}</a>.\n\n" );

		$order->save();

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Returns the configured xpub for the gateway, so new addresses can be generated.
	 *
	 * TODO: This should be ~`{master_public_key:string, wp_post_id?:int}`.
	 * TODO: rename to get_master_public_key() ?
	 *
	 * @used-by API::generate_new_addresses_for_wallet()
	 */
	public function get_xpub(): ?string {
		// TODO: validate xpub format when setting (in JS).
		return isset( $this->settings['xpub'] ) && is_string( $this->settings['xpub'] ) && ! empty( $this->settings['xpub'] )
			? $this->settings['xpub']
			: null;
	}

	/**
	 * Price margin is the allowable difference between the amount received and the amount expected.
	 *
	 * @used-by API::get_order_details()
	 *
	 * @return float
	 */
	public function get_price_margin_percent(): float {
		$price_margin = $this->settings['price_margin'];
		return is_numeric( $price_margin ) ? floatval( $price_margin ) : 2.0;
	}
}
