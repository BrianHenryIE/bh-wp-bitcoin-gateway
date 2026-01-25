<?php
/**
 * Make the payment gateway available to the new WooCommerce Blocks checkout.
 *
 * Mostly just registers a script.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use WC_Payment_Gateway;

/**
 * Instance of the class expected by PaymentMethodRegistry.
 *
 * @see PaymentMethodRegistry::register()
 * @see IntegrationRegistry::initialize()
 */
class Bitcoin_Gateway_Blocks_Checkout_Support extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var WC_Payment_Gateway&Bitcoin_Gateway
	 */
	protected $gateway;

	/**
	 * Constructor
	 *
	 * @param Bitcoin_Gateway    $gateway The gateway instance.
	 * @param API_Interface      $api      The API instance. Used to get exchange rate.
	 * @param Settings_Interface $plugin_settings The plugin settings. Used to get the plugin URL.
	 */
	public function __construct(
		Bitcoin_Gateway $gateway,
		protected API_Interface $api,
		protected Settings_Interface $plugin_settings
	) {
		$this->gateway = $gateway;
		$this->name    = $gateway->id;
	}

	/**
	 * Initializes the payment method type.
	 *
	 * @see IntegrationInterface::initialize()
	 */
	public function initialize(): void {
		$this->settings = $this->gateway->settings;
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @used-by PaymentMethodRegistry::get_all_active_registered()
	 */
	public function is_active(): bool {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array<string>
	 */
	public function get_payment_method_script_handles(): array {

		$handle = 'bh-wp-bitcoin-gateway-blocks';

		$script_url = $this->plugin_settings->get_plugin_url() . 'assets/js/frontend/woocommerce/blocks/checkout/gateway/gateway.min.js';

		$dependencies = array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' );
		$version      = $this->plugin_settings->get_plugin_version();

		wp_register_script( $handle, $script_url, $dependencies, $version, true );

		wp_set_script_translations( $handle, 'bh-wp-bitcoin-gateway', $this->plugin_settings->get_plugin_url() . 'languages/' );

		return array( $handle );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * Filters the `WC_Payment_Gateway::$supports` array using the instance's `supports()` function.
	 *
	 * @see \WC_Payment_Gateway::supports()
	 *
	 * @return array{title:string, description:string, supports:array<string>}
	 * @throws UnknownCurrencyException It'll almost definitely never happen.
	 */
	public function get_payment_method_data(): array {
		/** @var string $title */
		$title = $this->get_setting( 'title' );
		/** @var string $description */
		$description = $this->get_setting( 'description' );
		/** @var array<int|string, string> $supports */
		$supports = $this->gateway->supports;

		$currency = Currency::of(
			get_woocommerce_currency()
		);

		return array(
			'title'                     => $title,
			'description'               => $description,
			'supports'                  => $supports,
			'exchange_rate_information' => sprintf(
				'1 BTC = %s %s',
				get_woocommerce_currency(),
				wp_kses_decode_entities(
					wp_strip_all_tags(
						wc_price(
							$this->api->get_exchange_rate(
								$currency
							)?->getAmount()->toFloat() ?? 0.0 // TODO: add an immediately invoked function that alerts admins of a problem but doesn't break ux.
						)
					)
				)
			),
			'bitcoin_image_src'         => $this->gateway->icon,
		);
	}
}
