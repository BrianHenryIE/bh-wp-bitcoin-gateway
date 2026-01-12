<?php
/**
 * TODO: Removed *required on billing fields.
 *
 * @see https://github.com/helgatheviking/wc-remove-billing-address-on-free-checkout
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Repository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Checkout implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_WooCommerce_Interface  $api API functions for WooCommerce integration.
	 * @param Bitcoin_Wallet_Repository  $bitcoin_wallet_repository Repository for Bitcoin wallets.
	 * @param Bitcoin_Address_Repository $bitcoin_address_repository Repository for payment addresses.
	 * @param LoggerInterface            $logger PSR logger instance.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api,
		protected Bitcoin_Wallet_Repository $bitcoin_wallet_repository,
		protected Bitcoin_Address_Repository $bitcoin_address_repository,
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

			if ( ! $master_public_key ) {
				// TODO: log.
				continue;
			}

			// The assumption here is that there was a Wallet created when the xpub was saved in the UI.
			$wallet = $this->bitcoin_wallet_repository->get_by_xpub( $master_public_key );

			if ( ! $wallet ) {
				// TODO: log.
				continue;
			}

			$result = $this->api->ensure_unused_addresses_for_wallet( $wallet, 1 );
		}
	}
}
