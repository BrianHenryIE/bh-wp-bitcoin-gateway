<?php
/**
 * Meta keys used when saving Bitcoin_Transaction as a WP_Post.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

interface Bitcoin_Transaction_WP_Post_Interface {

	/**
	 * Post type names must be between 1 and 20 characters in length
	 * 'bh-bitcoin-transaction' is 22 characters
	 *
	 * @see https://github.com/WordPress/WordPress/blob/ec24ee6087dad52052c7d8a11d50c24c9ba89a3b/wp-includes/post.php#L1825-L1828
	 */
	const string POST_TYPE = 'bh-bitcoin-tx';

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
