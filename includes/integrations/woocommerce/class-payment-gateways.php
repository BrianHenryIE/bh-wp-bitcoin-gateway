<?php
/**
 * Add the payment gateway to WooCommerce's list of gateways.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * Add the payment gateway's class name to WooCommerce's list of gateways it will
 * later instantiate.
 */
class Payment_Gateways {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param API_Interface             $api
	 * @param API_WooCommerce_Interface $api To get the list of Bitcoin gateways to register with WooCommerce Blocks checkout.
	 * @param Settings_Interface        $settings Passed to {@see Bitcoin_Gateway_Blocks_Checkout_Support}.
	 * @param LoggerInterface           $logger A PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected API_WooCommerce_Interface $api_woocommerce,
		protected Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Add the Gateway to WooCommerce.
	 *
	 * @hooked woocommerce_payment_gateways
	 *
	 * @param string[] $gateways The payment gateways registered with WooCommerce.
	 *
	 * @return string[]
	 * @see WC_Payment_Gateways::init()
	 */
	public function add_to_woocommerce( array $gateways ): array {
		$gateways[] = new Bitcoin_Gateway(
			$this->api,
			$this->api_woocommerce,
			$this->settings,
			$this->logger
		);

		return $gateways;
	}

	/**
	 * Registers the gateway with WooCommerce Blocks checkout integration.
	 *
	 * It seems the `woocommerce_payment_gateways` filter is not run before this.
	 *
	 * @hooked woocommerce_blocks_payment_method_type_registration
	 *
	 * @param PaymentMethodRegistry $payment_method_registry WooCommerce class which handles blocks checkout gateways.
	 */
	public function register_woocommerce_block_checkout_support( PaymentMethodRegistry $payment_method_registry ): void {

		foreach ( $this->api_woocommerce->get_bitcoin_gateways() as $gateway ) {

			$support = new Bitcoin_Gateway_Blocks_Checkout_Support( $gateway, $this->api, $this->api_woocommerce, $this->settings );
			$payment_method_registry->register( $support );
		}
	}
}
