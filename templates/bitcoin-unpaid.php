<?php
/**
 * Template displaying a table with the Bitcoin address, QR code, amount required, amount received, status, and the time
 * last checked. CSS classes on each allow for JS to target the data for copying to the clipboard.
 *
 * @see \BrianHenryIE\WP_Bitcoin_Gateway\API_Interface::get_order_details()
 *
 * @var array{template_name:string, template_path:string, located:string, args:array<string,mixed>} $action_args
 * @var array<string, mixed> $args Associative array containing the result of `API_Interface::get_order_details()`, extracted into these variables:
 *
 * @var string $btc_logo_url
 * @var string $payment_status 'Awaiting Payment'|'Partly Paid'|'Payment seen, awaiting confirmation'|'Paid'.
 * @var string $payment_status_key 'awaiting_payment'|'partly_paid'|'awaiting_confirmation'|'paid'.
 * @var string $btc_address Destination payment address.
 * @var string $btc_address_href Hyperlink:`bitcoin:xpub?amount=0.0321`.
 * @var string $btc_total Order total in BTC.
 * @var string $btc_total_formatted Order total prefixed with "฿".
 * @var string $btc_exchange_rate_formatted The Bitcoin exchange rate with friendly thousand separators.
 * @var string $btc_amount_received Amount received at the destination address so far.
 * @var string $btc_amount_received_formatted Amount received prefixed with "฿".
 * @var string $btc_amount_unconfirmed_formatted Amount seen but not yet confirmed, prefixed with "฿".
 * @var string $last_checked_time_formatted The last time a blockchain service was queried for updates to the payment address.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

use BrianHenryIE\WP_Bitcoin_Gateway\chillerlan\QRCode\QRCode;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

?>

<div class="bh-wp-bitcoin-gateway-details">

	<?php // For scrolling to? ?>
	<a id="bh_wp_bitcoin_gateway"></a>

	<?php
	// Once the full payment has been seen (even unconfirmed) there is nothing more to pay, so the QR code goes.
	$bh_wp_bitcoin_gateway_payment_seen = in_array( $payment_status_key, array( 'awaiting_confirmation', 'paid' ), true );
	?>

	<div class="bh_wp_bitcoin_gateway_logo_qr" data-payment-status-key="<?php echo esc_attr( $payment_status_key ); ?>">
	<img alt="Bitcoin logo" class="bh_wp_bitcoin_gateway_logo" src="<?php echo esc_attr( $btc_logo_url ); ?>">

	<a class="bh_wp_bitcoin_gateway_qr" href="<?php echo esc_url( $btc_address_href, array( 'bitcoin' ) ); ?>" <?php echo $bh_wp_bitcoin_gateway_payment_seen ? 'style="display:none"' : ''; ?>>
		<img src="<?php echo esc_attr( new QRCode()->render( $btc_address_href ) ); ?>" alt="<?php esc_attr_e( 'Payment QR Code', 'bh-wp-bitcoin-gateway' ); ?>" />
	</a>

	<p class="bh_wp_bitcoin_gateway_payment_seen" <?php echo $bh_wp_bitcoin_gateway_payment_seen ? '' : 'style="display:none"'; ?>>
		<?php esc_html_e( 'Thank you, your payment has been seen on the Bitcoin network. Your order will be processed once the payment has been confirmed.', 'bh-wp-bitcoin-gateway' ); ?>
	</p>

	<p class="bh_wp_bitcoin_gateway_polling_stopped" style="display:none">
		<?php esc_html_e( 'Automatic checking for your payment has stopped. Click "Last Checked" below to check again, or reload this page.', 'bh-wp-bitcoin-gateway' ); ?>
	</p>
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
			<td><span class=""><?php esc_html_e( 'Awaiting Confirmation:', 'bh-wp-bitcoin-gateway' ); ?></span></td>
			<td><span class="bh_wp_bitcoin_gateway_amount_unconfirmed bh_wp_bitcoin_gateway_updatable"><?php echo esc_html( $btc_amount_unconfirmed_formatted ); ?></span></td>
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

