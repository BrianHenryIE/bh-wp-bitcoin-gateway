<?php
/**
 * Template to display in emails to customers.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\API_Interface::get_order_details()
 *
 * @var array<string, mixed> $args Associative array containing the result of `API_Interface::get_formatted_order_details()`, extracted into these variables:
 *
 * @var string $payment_status 'Awaiting Payment'|'Partially Paid'|'Paid'.
 * @var string $btc_address Destination payment address.
 * @var string $btc_total Order total in BTC.
 * @var string $btc_total_formatted Order total prefixed with "฿".
 * @var string $btc_exchange_rate_formatted The Bitcoin exchange rate with friendly thousand separators.
 * @var string $btc_amount_received Amount received at the destination address so far.
 * @var string $btc_amount_received_formatted Amount received prefixed with "฿".
 * @var WC_Order $bitcoin_order The order that Bitcoin is being used to pay.
 *
 * @var string $exchange_rate_url
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

if ( ! $bitcoin_order->is_paid() ) :

	$bitcoin_href_address = 'bitcoin:' . $btc_address . '?amount=' . $btc_total;

	?>

	<p>Bitcoin price: <b><?php echo esc_html( $btc_total_formatted ); ?></b> (1 BTC = <?php echo esc_html( wp_strip_all_tags( $btc_exchange_rate_formatted ) ); ?>).</p>

	<p>Payment Address: <a target="_blank" href="<?php echo esc_url( $bitcoin_href_address, array( 'bitcoin' ) ); ?>"><?php echo esc_html( $btc_address ); ?></a></p>

	<?php
endif;
