<?php
/**
 * Constants for order meta-keys.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

/**
 * Defines constants for meta-keys.
 */
interface Order {

	const string BITCOIN_ADDRESS_META_KEY = 'bh_wp_bitcoin_gateway_address';

	const string EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_exchange_rate_at_time_of_purchase';

	const string ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_total_at_time_of_purchase';

	const string BITCOIN_AMOUNT_RECEIVED_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_amount_received';

	const string LAST_CHECKED_META_KEY = 'bh_wp_bitcoin_gateway_last_checked_time';
}
