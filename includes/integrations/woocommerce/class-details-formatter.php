<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order_Interface;
use NumberFormatter;

// $currency    = $order_details['currency'];
// $fiat_symbol = get_woocommerce_currency_symbol( $currency );
//
// TODO: Find a WooCommerce function which correctly places the currency symbol before/after.
// $btc_price_at_at_order_time = $fiat_symbol . ' ' . $order_details['exchange_rate'];
// $fiat_formatted_price       = $order->get_formatted_order_total();
// $btc_price                  = $order_details['btc_price'];
// $bitcoin_formatted_price    = $btc_symbol . wc_format_decimal( $btc_price, $round_btc );
//
// $btc_logo_url = $site_url . '/wp-content/plugins/bh-wp-bitcoin-gateway/assets/bitcoin.png';

class Details_Formatter {
	private WC_Bitcoin_Order_Interface $order;

	public function __construct( WC_Bitcoin_Order_Interface $order ) {
		$this->order = $order;
	}

	/**
	 * ฿ U+0E3F THAI CURRENCY SYMBOL BAHT, decimal: 3647, HTML: &#3647;, UTF-8: 0xE0 0xB8 0xBF, block: Thai.
	 */
	public function get_btc_total_formatted(): string {
		return $this->format_money_to_bitcoin(
			$this->order->get_btc_total_price()
		);
	}

	/**
	 * Returns "฿ 0.00001234" style formatted Bitcoin amount.
	 *
	 * @param Money $money The amount to format.
	 *
	 * @return string
	 */
	protected function format_money_to_bitcoin( Money $money ): string {
		$btc_symbol = '฿';
		$pattern    = '¤#,##0.000000000000000000';

		$btc_formatter = new NumberFormatter( 'en_US', NumberFormatter::DECIMAL );
		/**
		 * "Bitcoin has 8 decimal places."
		 *
		 * @see https://bitcoin.stackexchange.com/a/31934
		 */
		$btc_formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, 8 );

		$formatted = $money->formatWith( $btc_formatter );

		return $btc_symbol . ' ' . wc_trim_zeros( $formatted );
	}

	public function get_btc_exchange_rate_formatted(): string {
		return $this->order->get_currency() . ' ' . wc_price( $this->order->get_btc_exchange_rate()->toFloat(), array( 'currency' => $this->order->get_currency() ) );
	}

	/**
	 * Get the pretty formatted WooCommerce order status.
	 */
	public function get_wc_order_status_formatted(): ?string {
		return wc_get_order_statuses()[ 'wc-' . $this->order->get_status() ] ?? null;
	}

	public function get_last_checked_time_formatted(): string {
		if ( is_null( $this->order->get_last_checked_time() ) ) {
			return __( 'Never', 'bh-wp-bitcoin-gateway' );
		}
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		$timezone    = wp_timezone_string();
		// $last_checked_time is in UTC... change it to local time.?
		// The server time is not local time... maybe use their address?
		// @see https://stackoverflow.com/tags/timezone/info
		return $this->order->get_last_checked_time()->format( $date_format . ', ' . $time_format ) . ' ' . $timezone;
	}

	public function get_btc_address_derivation_path_sequence_number(): string {
		$sequence_number = $this->order->get_address()->get_derivation_path_sequence_number();
		return "{$sequence_number}";
	}

	public function get_xpub_js_span(): string {
		$xpub                  = $this->order->get_address()->get_raw_address();
		$xpub_friendly_display = substr( $xpub, 0, 7 ) . ' ... ' . substr( $xpub, - 3, 3 );
		return "<span style=\"border-bottom: 1px dashed #999; word-wrap: break-word\" onclick=\"this.innerText = this.innerText === '{$xpub}' ? '{$xpub_friendly_display}' : '{$xpub}';\" title=\"{$xpub}\"'>{$xpub_friendly_display}</span>";
	}

	/**
	 *  Add a link showing the exchange rate around the time of the order ( -12 hours to +12 hours after payment).
	 */
	public function get_exchange_rate_url(): string {
		/**
		 * This supposedly could be null, but I can't imagine a scenario where WooCommerce returns an order object
		 * that doesn't have a DateTime for created.
		 *
		 * @var \DateTimeInterface $date_created
		 */
		$date_created = $this->order->get_date_created();
		$from         = $date_created->getTimestamp() - ( DAY_IN_SECONDS / 2 );
		if ( ! is_null( $this->order->get_date_paid() ) ) {
			$to = $this->order->get_date_paid()->getTimestamp() + ( DAY_IN_SECONDS / 2 );
		} else {
			$to = $from + DAY_IN_SECONDS;
		}
		return "https://www.blockchain.com/prices/BTC?from={$from}&to={$to}&timeSpan=custom&scale=0&style=line";
	}

	public function get_btc_amount_received_formatted(): string {

		// TODO: An address doesn't know how many confirmations an order wants.
		// e.g. there could be dynamic number of confirmations based on order total

		return $this->format_money_to_bitcoin(
			$this->order->get_address()->get_amount_received() ?? Money::of( 0, 'BTC' )
		);
	}

	/**
	 * @return string 'Awaiting Payment'|'Partly Paid'|'Paid'
	 */
	public function get_friendly_status(): string {

		// If the order is not marked paid, but has transactions, it is partly-paid.
		switch ( true ) {
			case $this->order->is_paid():
				$result = __( 'Paid', 'bh-wp-bitcoin-gateway' );
				break;
			case $this->order->get_address()->get_amount_received()?->isGreaterThan( Money::of( 0, 'BTC' ) ):
				$result = __( 'Partly Paid', 'bh-wp-bitcoin-gateway' );
				break;
			default:
				$result = __( 'Awaiting Payment', 'bh-wp-bitcoin-gateway' );
		}

		return $result;
	}

	/**
	 * @return array{btc_total_formatted:string, btc_exchange_rate_formatted:string, order_status_before_formatted:string, order_status_formatted:string, btc_amount_received_formatted:string, last_checked_time_formatted:string}
	 */
	public function to_array( bool $as_camel_case = false ): array {

		$result                                  = array();
		$result['btc_total_formatted']           = $this->get_btc_total_formatted();
		$result['btc_exchange_rate_formatted']   = $this->get_btc_exchange_rate_formatted();
		$result['order_status_formatted']        = $this->get_wc_order_status_formatted();
		$result['btc_amount_received_formatted'] = $this->get_btc_amount_received_formatted();
		$result['last_checked_time_formatted']   = $this->get_last_checked_time_formatted();
		$result['btc_address_derivation_path_sequence_number'] = $this->get_btc_address_derivation_path_sequence_number();
		$result['parent_wallet_xpub_html']                     = $this->get_xpub_js_span();
		$result['exchange_rate_url']                           = $this->get_exchange_rate_url();
		$result['payment_status']                              = $this->get_friendly_status();
		$result['payment_address']                             = $this->order->get_address()->get_raw_address();

		if ( $as_camel_case ) {
			foreach ( $result as $key => $value ) {
				$new_key            = $this->as_camel_case( $key );
				$result[ $new_key ] = $value;
				unset( $result[ $key ] );
			}
		}

		return $result;
	}

	/**
	 * Map the array keys to camelCase for JavaScript use.
	 *
	 * @param string $variable_name Snake_case variable name.
	 *
	 * @return string CamelCase variable name.
	 */
	protected function as_camel_case( string $variable_name ): string {
		return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $variable_name ) ) ) );
	}
}
