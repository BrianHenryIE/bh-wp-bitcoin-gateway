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
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Throwable;
use WC_Payment_Gateway;

/**
 * Instance of the class expected by PaymentMethodRegistry.
 *
 * @see PaymentMethodRegistry::register()
 * @see IntegrationRegistry::initialize()
 */
class Bitcoin_Gateway_Blocks_Checkout_Support extends AbstractPaymentMethodType {
	use LoggerAwareTrait;

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
	 * @param LoggerInterface    $logger A PSR logger.
	 */
	public function __construct(
		Bitcoin_Gateway $gateway,
		protected API_Interface $api,
		protected Settings_Interface $plugin_settings,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
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
		try {
			return $this->gateway->is_available();
		} catch ( Throwable $throwable ) {
			$this->logger->error(
				'Error determining blocks checkout availability, gateway will be hidden: ' . $throwable->getMessage(),
				array( 'exception' => $throwable )
			);
			return false;
		}
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

		// TODO: Should this be the file system path rather than the URL?!
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
	 * @return array{title:string, description:string, supports:array<string>, exchange_rate_information:string, bitcoin_image_src:string}
	 */
	public function get_payment_method_data(): array {
		/** @var string $title */
		$title = $this->get_setting( 'title' );
		/** @var string $description */
		$description = $this->get_setting( 'description' );
		/** @var array<int|string, string> $supports */
		$supports = $this->gateway->supports;

		return array(
			'title'                     => $title,
			'description'               => $description,
			'supports'                  => $supports,
			'exchange_rate_information' => $this->get_exchange_rate_information(),
			'bitcoin_image_src'         => $this->gateway->icon,
		);
	}

	/**
	 * E.g. "1 BTC = USD $100,000". Empty string when no rate is available; never throws, this is built on every
	 * blocks cart and checkout page load.
	 */
	protected function get_exchange_rate_information(): string {
		try {
			$exchange_rate = $this->api->get_exchange_rate( Currency::of( get_woocommerce_currency() ) );

			if ( is_null( $exchange_rate ) ) {
				return '';
			}

			return sprintf(
				'1 BTC = %s %s',
				get_woocommerce_currency(),
				wp_kses_decode_entities( wp_strip_all_tags( wc_price( $exchange_rate->getAmount()->toFloat() ) ) )
			);
		} catch ( Throwable $throwable ) {
			$this->logger->error(
				'Error building exchange rate information for blocks checkout: ' . $throwable->getMessage(),
				array( 'exception' => $throwable )
			);
			return '';
		}
	}
}
