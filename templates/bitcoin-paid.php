<?php
/**
 * Friendly info table to display after the order is considered paid.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\API_Interface::get_order_details()
 *
 * @var array<string, mixed> $args Associative array containing the result of `API_Interface::get_order_details()`, extracted into these variables:
 *
 * @var string $btc_logo_url // TODO
 * @var string $payment_status 'Awaiting Payment'|'Partially Paid'|'Paid'.
 * @var string $btc_address Destination payment address.
 * @var string $btc_total Order total in BTC.
 * @var string $btc_total_formatted Order total prefixed with "฿".
 * @var string $btc_exchange_rate_formatted // TODO: Format it! The Bitcoin exchange rate with friendly thousand separators.
 * @var string $btc_amount_received Amount received at the destination address so far.
 * @var string $btc_amount_received_formatted Amount received prefixed with "฿".
 * @var string $last_checked_time_formatted The last time a blockchain service was queried for updates to the payment address.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// TODO.
