<?php
/**
 * The meta fields used to save a Bitcoin_Address in a WP_Post.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;

interface Bitcoin_Address_WP_Post_Interface {

	const string POST_TYPE                                = 'bh-bitcoin-address';
	const string DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY = 'derivation_path_sequence_number';

	/**
	 * Saved in meta as array<int, string> <wp_post_id, tx_id>.
	 *
	 * @see Bitcoin_Transaction::get_txid()
	 * @see Bitcoin_Address::get_tx_ids()
	 * @see Bitcoin_Wallet_Service::update_address_transactions_posts()
	 * @see Payment_Service::update_address_transactions()
	 */
	const string TRANSACTIONS_META_KEY  = 'address_transactions';
	const string BALANCE_META_KEY       = 'balance';
	const string ORDER_ID_META_KEY      = 'order_id';
	const string TARGET_AMOUNT_META_KEY = 'target_amount';
}
