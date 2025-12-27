<?php
/**
 * The meta fields used to save a Bitcoin_Address in a WP_Post.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

interface Bitcoin_Address_WP_Post_Interface {

	const string POST_TYPE = 'bh-bitcoin-address';

	const string TRANSACTIONS_META_KEY                    = 'address_transactions';
	const string DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY = 'derivation_path_sequence_number';
	const string TARGET_AMOUNT_META_KEY                   = 'target_amount';
	const string BALANCE_META_KEY                         = 'balance';
	const string ORDER_ID_META_KEY                        = 'order_id';
}
