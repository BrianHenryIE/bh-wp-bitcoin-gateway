<?php
/**
 * Hook into the addresses post type list table to add links to the gateways used for those addresses.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table::column_gateways()
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * Link to the gateway recorded to the wallet.
 */
class Addresses_List_Table {

	/**
	 * Provide href, text for a link to the gateway used by a wallet.
	 *
	 * `add_filter( 'bh_wp_bitcoin_gateway_gateway_link', 'woocommerce_gateway_link', 10, 5 );`.
	 *
	 * @used-by \BrianHenryIE\WP_Bitcoin_Gateway\Admin\Addresses_List_Table::column_gateways()
	 * @hooked bh_wp_bitcoin_gateway_gateway_link
	 *
	 * @param array{href?:string|non-empty-string,text:string|non-empty-string} $filtered_result Presumably the array with shape, with empty values.
	 * @param string|class-string                                               $integration The recorded integration, that we should check is this us before proceeding.
	 * @param non-empty-string                                                  $gateway_id The id of the gateway being used with the wallet, which we will use to set the href.
	 * @param Bitcoin_Wallet                                                    $_bitcoin_wallet The wallet itself.
	 * @param ?Bitcoin_Address                                                  $_address The payment address, when we're working with a table with changing address each row.
	 * @return array{href?:string,text:string}
	 */
	public function woocommerce_gateway_link(
		array $filtered_result,
		string $integration,
		string $gateway_id,
		Bitcoin_Wallet $_bitcoin_wallet,
		?Bitcoin_Address $_address = null
	): array {
		if ( WooCommerce_Integration::class !== $integration ) {
			return $filtered_result;
		}

		if ( ! isset( WC_Payment_Gateways::instance()->get_available_payment_gateways()[ $gateway_id ] ) ) {
			return array(
				'text' => sprintf( 'WooCommerce: %s (unavailable)', $gateway_id ),
			);
		}

		/** @var WC_Payment_Gateway $gateway_instance */
		$gateway_instance = WC_Payment_Gateways::instance()->get_available_payment_gateways()[ $gateway_id ];

		return array(
			'href' => admin_url( sprintf( 'admin.php?page=wc-settings&tab=checkout&section=%s', $gateway_instance->id ) ),
			'text' => sprintf( 'WooCommerce: %s', $gateway_instance->title ),
		);
	}
}
