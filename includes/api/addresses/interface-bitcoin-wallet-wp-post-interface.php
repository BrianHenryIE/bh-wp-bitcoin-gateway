<?php
/**
 * Meta keys used when saving Bitcoin_Wallet as a WP_Post.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

interface Bitcoin_Wallet_WP_Post_Interface {

	const string POST_TYPE = 'bh-bitcoin-wallet';

	/**
	 * TODO: We are not yet setting the balance.
	 */
	const string BALANCE_META_KEY                    = 'bitcoin_wallet_balance';
	const string LAST_DERIVED_ADDRESS_INDEX_META_KEY = 'last_derived_address_index';

	/**
	 * Meta key to store the payment gateway ids this wallet is used with.
	 * `get_post_meta( $wallet_post_id, 'payment_gateway_ids', false )` returns an array of gateway ids.
	 *
	 * TODO: would this be better done as a taxonomy?
	 */
	const string GATEWAY_IDS_META_KEY = 'payment_gateway_ids';
}
