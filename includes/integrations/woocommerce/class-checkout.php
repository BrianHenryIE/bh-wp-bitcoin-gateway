<?php
/**
 * TODO: Removed *required on billing fields.
 *
 * @see https://github.com/helgatheviking/wc-remove-billing-address-on-free-checkout
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * When the purchase button is clicked, make one or more query-transaction requests to find an unused address.
 * Normally, there will be one address lined up ready to check, and in the unlilkey case that that address is now
 * used, the next one will almost definitely be unused.
 */
class Checkout implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_WooCommerce_Interface $api API functions for WooCommerce integration.
	 * @param LoggerInterface           $logger PSR logger instance.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * When the checkout is loaded, synchronously ensure there is one address available so the gateway displays.
	 *
	 * This hopefully will never be the case because of scheduled jobs regularly checking.
	 *
	 * There is still the possibility of two customers at the checkout vying for the one address, but a second address
	 * would be created/checked/found after "Place Order" is clicked anyway.
	 *
	 * @hooked woocommerce_checkout_init
	 */
	public function ensure_one_address_for_payment(): void {

		$bitcoin_gateways = $this->api->get_bitcoin_gateways();

		foreach ( $bitcoin_gateways as $bitcoin_gateway ) {
			/**
			 * Distinct from {@see Bitcoin_Gateway::is_available()} which checks the number of unused addresses.
			 */
			if ( 'yes' !== $bitcoin_gateway->enabled ) {
				continue;
			}

			$master_public_key = $bitcoin_gateway->get_xpub();

			// Probably a new gateway that is not configured.
			if ( ! $master_public_key ) {
				// TODO: log.
				continue;
			}

			// Although it's safe to assume here that there was a Wallet created when the xpub was saved in the UI,
			// this would create it anyway.
			$wallet_result = $this->api->get_wallet_for_master_public_key( $master_public_key );

			if ( ! $wallet_result->did_schedule_ensure_addresses ) {
				$this->api->ensure_unused_addresses_for_wallet( $wallet_result->wallet, 1 );
			}
		}
	}
}
