<?php
/**
 * TODO: Removed *required on billing fields.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Repository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Checkout implements LoggerAwareInterface {
	use LoggerAwareTrait;

	public function __construct(
		protected API_WooCommerce_Interface $api,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * When the checkout is loaded, synchronously ensure there is one address available.
	 *
	 * TODO: Check do this hook work for blocks checkout.
	 * TODO: Do the same check async on woocommerce_before_cart
	 *
	 * @hooked woocommerce_before_checkout_form
	 */
	public function generate_one_address_for_payment(): void {

		$bitcoin_gateways = $this->api->get_bitcoin_gateways();

		$bitcoin_wallet_factory     = new Bitcoin_Wallet_Factory();
		$bitcoin_wallet_repository  = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );
		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		foreach ( $bitcoin_gateways as $bitcoin_gateway ) {
			if ( 'yes' != $bitcoin_gateway->enabled ) {
				continue;
			}

			$gateway_xpub = $bitcoin_gateway->get_xpub();

			if ( ! $gateway_xpub ) {
				// TODO: log.
				continue;
			}

			// The assumption here is that there was a Wallet created when the xpub was saved in the UI.
			$wallet = $bitcoin_wallet_repository->get_by_xpub( $gateway_xpub );

			if ( ! $wallet ) {
				// TODO: log.
				continue;
			}

			$unused_addresses = $bitcoin_address_repository->get_addresses( $wallet, Bitcoin_Address_Status::UNUSED );

			if ( count( $unused_addresses ) > 0 ) {
				continue;
			}
			$this->api->generate_new_addresses_for_wallet( $wallet, 1 );

			// TODO Start background job to generate 10.
		}
	}
}
