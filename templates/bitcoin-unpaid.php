<?php
/**
 * Template displaying a table with the Bitcoin address, QR code, amount required, amount received, status, and the time
 * last checked. CSS classes on each allow for JS to target the data for copying to the clipboard.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\API_Interface::get_order_details()
 *
 * @var array{template_name:string, template_path:string, located:string, args:array} $action_args
 * @var array<string, mixed> $args Associative array containing the result of `API_Interface::get_order_details()`, extracted into these variables:
 *
 * @var string $btc_logo_url // TODO
 * @var string $payment_status 'Awaiting Payment'|'Partially Paid'|'Paid'.
 * @var string $btc_address Destination payment address.
 * @var string $btc_total Order total in BTC.
 * @var string $btc_total_formatted Order total prefixed with "฿".
 * @var string $btc_exchange_rate_formatted The Bitcoin exchange rate with friendly thousand separators.
 * @var string $btc_amount_received Amount received at the destination address so far.
 * @var string $btc_amount_received_formatted Amount received prefixed with "฿".
 * @var string $last_checked_time_formatted The last time a blockchain service was queried for updates to the payment address.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

use BrianHenryIE\WP_Bitcoin_Gateway\chillerlan\QRCode\QRCode;

$bitcoin_href_address = 'bitcoin:' . $btc_address . '?amount=' . $btc_total;

$btc_logo_url = BH_WP_BITCOIN_GATEWAY_URL . '/assets/bitcoin.png';
?>

<div class="bh-wp-bitcoin-gateway-details">

	<?php // For scrolling to? ?>
	<a id="bh_wp_bitcoin_gateway"></a>

	<div class="bh_wp_bitcoin_gateway_logo_qr">
	<img alt="Bitcoin logo" class="bh_wp_bitcoin_gateway_logo" src="<?php echo esc_attr( $btc_logo_url ); ?>">

	<a href="<?php echo esc_url( $bitcoin_href_address, array( 'bitcoin' ) ); ?>">
		<img src="<?php echo esc_attr( new QRCode()->render( $bitcoin_href_address ) ); ?>" alt="<?php esc_attr_e( 'Payment QR Code', 'bh-wp-bitcoin-gateway' ); ?>" />
	</a>
	</div>

	<table>
		<tr>
			<td><span class=""><?php esc_html_e( 'Payment Address:', 'bh-wp-bitcoin-gateway' ); ?></span></td>
			<td><span class="bh_wp_bitcoin_gateway_address"><?php echo esc_html( $btc_address ); ?></span></td>
		</tr>
		<tr>
			<td><span class=""><?php esc_html_e( 'Payment Total:', 'bh-wp-bitcoin-gateway' ); ?></span></td>
			<td><span class="bh_wp_bitcoin_gateway_total"><?php echo esc_html( $btc_total_formatted ); ?></span></td>
		</tr>
		<tr>
			<td><span class=""><?php esc_html_e( 'Amount Received:', 'bh-wp-bitcoin-gateway' ); ?></span></td>
			<td><span class="bh_wp_bitcoin_gateway_amount_received bh_wp_bitcoin_gateway_updatable"><?php echo esc_html( $btc_amount_received_formatted ); ?></span></td>
		</tr>
		<tr>
			<td><span class=""><?php esc_html_e( 'Status:', 'bh-wp-bitcoin-gateway' ); ?></span></td>
			<td><span class="bh_wp_bitcoin_gateway_status bh_wp_bitcoin_gateway_updatable">
			<?php
			echo esc_html( $payment_status );
			?>
			</span></td>
		</tr>
		<tr>
			<td><span class=""><?php esc_html_e( 'Last Checked:', 'bh-wp-bitcoin-gateway' ); ?></span></td>
			<td><span class="bh_wp_bitcoin_gateway_last_checked_time bh_wp_bitcoin_gateway_updatable">
				<?php echo esc_html( $last_checked_time_formatted ); ?></span>
			</td>
		</tr>

	</table>

	<?php do_action( 'bh_wp_bitcoin_gateway_template_bitcoin_unpaid_after_table', $args['template'], $args ); ?>

	<p>Exchange rate at time of order: 1 BTC = <?php echo wp_kses_post( $btc_exchange_rate_formatted ); ?></p>

</div>

