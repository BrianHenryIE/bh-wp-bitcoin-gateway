<?php
/**
 * Meta keys used when saving Bitcoin_Transaction as a WP_Post.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

interface Bitcoin_Transaction_WP_Post_Interface {

	const string POST_TYPE = 'bh-bitcoin-transaction';

	const string BLOCK_HEIGHT_META_KEY = 'bitcoin_transaction_block_height';

	/**
	 * TODO: What is "value" here? Does "value" only make sense in context of a specific Bitcoin_Address?
	 */
	const string VALUE_META_KEY = 'value';

	const string BLOCK_DATETIME_META_KEY = 'blockchain_datetime';

	/**
	 * Store a list of <post_id:bitcoin_address_xpub> in post meta.
	 */
	const string BITCOIN_ADDRESSES_POST_IDS_META_KEY = 'bitcoin_addresses_post_ids';
}
