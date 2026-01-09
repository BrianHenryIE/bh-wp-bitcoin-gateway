<?php
/**
 * Takes the order details and return HTML and plain strings for output.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order_Interface;
use NumberFormatter;

/**
 * Parse the values of WC_Bitcoin_Order_Interface into HTML and strings for output.
 */
class Details_Formatter {

	/**
	 * Constructor
	 *
	 * @param WC_Bitcoin_Order_Interface $order The order we are about to print.
	 */
	public function __construct(
		protected WC_Bitcoin_Order_Interface $order
	) {
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

	/**
	 * TODO: This should display the store currency value for one Bitcoin at the time of order. Currently ~"90817.00".
	 */
	public function get_btc_exchange_rate_formatted(): string {
		return $this->order->get_currency() . ' ' . wc_price( $this->order->get_btc_exchange_rate()->toFloat(), array( 'currency' => $this->order->get_currency() ) );
	}

	/**
	 * Get the pretty formatted WooCommerce order status.
	 */
	public function get_wc_order_status_formatted(): ?string {
		/** @var array<string, string> $wc_order_statuses */
		$wc_order_statuses = wc_get_order_statuses();

		return $wc_order_statuses[ 'wc-' . $this->order->get_status() ] ?? null;
	}

	/**
	 * "Never"|the time it was last checked. TODO: this is using `order->get_last_checked_time` which is not
	 * updated when the Bitcoin_Address itself has last been checked for transactions.
	 */
	public function get_last_checked_time_formatted(): string {
		if ( is_null( $this->order->get_last_checked_time() ) ) {
			return __( 'Never', 'bh-wp-bitcoin-gateway' );
		}
		/**
		 * @see https://www.php.net/manual/datetime.format.php
		 * @var string $date_format
		 */
		$date_format = get_option( 'date_format' ) ?: 'Y-m-d'; // TODO: What is a good default here?
		/**
		 * @see https://www.php.net/manual/datetime.format.php
		 * @var string $time_format
		 */
		$time_format = get_option( 'time_format' ) ?: 'H:i:s'; // TODO: default format.
		$timezone    = wp_timezone_string();
		// $last_checked_time is in UTC... change it to local time.?
		// The server time is not local time... maybe use their address?
		// @see https://stackoverflow.com/tags/timezone/info

		$last_checked = $this->order->get_last_checked_time();
		// @phpstan-ignore-next-line For some reason PHPStan isn't convinced this can be null.
		if ( is_null( $last_checked ) ) {
			return __( 'Never', 'bh-wp-bitcoin-gateway' );
		}

		$date_time_formatted = $last_checked->format( $date_format . ', ' . $time_format );

		return $date_time_formatted . ' ' . $timezone;
	}

	/**
	 * The index of the derived address being used. TODO: no point displaying this to customers.
	 */
	public function get_btc_address_derivation_path_sequence_number(): string {
		$sequence_number = $this->order->get_address()->get_derivation_path_sequence_number();
		return "{$sequence_number}";
	}

	/**
	 * Get a clickable HTML element, to copy the payment address to the clipboard when clicked.
	 */
	public function get_xpub_js_span(): string {
		$payment_address                  = $this->order->get_address()->get_raw_address();
		$payment_address_friendly_display = substr( $payment_address, 0, 7 ) . ' ... ' . substr( $payment_address, - 3, 3 );
		return "<span style=\"border-bottom: 1px dashed #999; word-wrap: break-word\" onclick=\"this.innerText = this.innerText === '{$payment_address}' ? '{$payment_address_friendly_display}' : '{$payment_address}';\" title=\"{$payment_address}\"'>{$payment_address_friendly_display}</span>";
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

	/**
	 * String as ฿0.00123.
	 *
	 * @throws UnknownCurrencyException If 'BTC' has not been added to `brick/money`.
	 */
	public function get_btc_amount_received_formatted(): string {

		// TODO: An address doesn't know how many confirmations an order wants.
		// e.g. there could be dynamic number of confirmations based on order total.

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
	 * Get all values this class creates as an array.
	 *
	 * Probably passed to a template.
	 *
	 * probably: array{btc_total_formatted:string, btc_exchange_rate_formatted:string, order_status_before_formatted:string, order_status_formatted:string|null, btc_amount_received_formatted:string, last_checked_time_formatted:string}.
	 * maybe: camel case keys.
	 *
	 * @param bool $as_camel_case Default to snake_case but the array keys can be camelCase too.
	 *
	 * @return array<string, string|null>
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
