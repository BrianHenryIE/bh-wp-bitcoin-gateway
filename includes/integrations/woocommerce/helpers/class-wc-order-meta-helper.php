<?php
/**
 * Constants for order meta-keys. Helper functions to return typed values.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Helpers;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use WC_Order;

/**
 * Helper functions so the format the data is saved in meta can be changed later.
 */
class WC_Order_Meta_Helper implements LoggerAwareInterface {
	use LoggerAwareTrait;

	const string BITCOIN_ADDRESS_META_KEY = 'bh_wp_bitcoin_gateway_payment_address';

	const string EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_exchange_rate_at_time_of_purchase';

	const string ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_total_at_time_of_purchase';

	const string BITCOIN_AMOUNT_CONFIRMED_RECEIVED_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_amount_confirmed_received';

	/**
	 * Save the payment address string to order meta.
	 *
	 * @param WC_Order        $wc_order The WooCommerce order.
	 * @param Bitcoin_Address $payment_address The address object to read the address string from.
	 */
	public function set_raw_address( WC_Order $wc_order, Bitcoin_Address $payment_address ): void {
		$wc_order->add_meta_data( self::BITCOIN_ADDRESS_META_KEY, $payment_address->get_raw_address() );
		$wc_order->save();
	}

	/**
	 * Get the saved payment address, if present.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 */
	public function get_raw_payment_address( WC_Order $wc_order ): ?string {
		$payment_address_meta = $wc_order->get_meta( self::BITCOIN_ADDRESS_META_KEY );
		return is_string( $payment_address_meta ) ? $payment_address_meta : null;
	}

	/**
	 * Save the order value in BTC.
	 *
	 * @see Bitcoin_Address::$target_amount
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param Money    $btc_total The order total in BTC.
	 */
	public function set_btc_total_price( WC_Order $wc_order, Money $btc_total ): void {
		// Record the amount the customer has been asked to pay in BTC.
		$wc_order->add_meta_data(
			self::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY,
			$btc_total->jsonSerialize()
		);
		$wc_order->save();
	}

	/**
	 * The order price in Bitcoin at the time of purchase.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 *
	 * @return ?Money The order total in BTC.
	 */
	public function get_btc_total_price( WC_Order $wc_order ): ?Money {
		/** @var array{amount:string, currency:string} $btc_total */
		$btc_total = $wc_order->get_meta( self::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY );

		return Money::of( $btc_total['amount'], $btc_total['currency'] );
	}

	/**
	 * Save the exchange rate used to calculate the expected payment amount.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param Money    $exchange_rate 1 BTC = x.
	 */
	public function set_exchange_rate( WC_Order $wc_order, Money $exchange_rate ): void {
		$wc_order->add_meta_data(
			self::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY,
			$exchange_rate->jsonSerialize()
		);
		$wc_order->save();
	}

	/**
	 * Get the Bitcoin to fiat exchange rate for this order.
	 * The price of 1 Bitcoin at the time of purchase.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return ?Money The exchange rate.
	 */
	public function get_exchange_rate( WC_Order $wc_order ): ?Money {
		/** @var array{amount:string, currency:string} $rate_meta */
		$rate_meta = $wc_order->get_meta( self::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY );

		return Money::of( $rate_meta['amount'], $rate_meta['currency'] );
	}

	/**
	 * Set the confirmed Bitcoin amount received for this order.
	 *
	 * @param WC_Order $wc_order The order to save the meta on.
	 * @param Money    $updated_confirmed_value The confirmed amount received in Bitcoin.
	 */
	public function set_amount_received( WC_Order $wc_order, Money $updated_confirmed_value ): void {
		$wc_order->add_meta_data( self::BITCOIN_AMOUNT_CONFIRMED_RECEIVED_META_KEY, $updated_confirmed_value, true );
		$wc_order->save();
	}

	/**
	 * Get the total value with the required number of confirmations at the last checked time.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 */
	public function get_amount_received( WC_Order $wc_order ): ?Money {
		/** @var array{amount:string, currency:string} $confirmed_amount_meta */
		$confirmed_amount_meta = $wc_order->get_meta( self::BITCOIN_AMOUNT_CONFIRMED_RECEIVED_META_KEY );

		return Money::of( $confirmed_amount_meta['amount'], $confirmed_amount_meta['currency'] );
	}
}
