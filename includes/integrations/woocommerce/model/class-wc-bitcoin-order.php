<?php
/**
 * Constants for order meta-keys. Helper functions to return typed values.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperInterface;
use DateTimeInterface;
use Exception;
use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * WC_Order with Bitcoin order functions.
 */
class WC_Bitcoin_Order extends WC_Order {

	const string BITCOIN_ADDRESS_META_KEY = 'bh_wp_bitcoin_gateway_payment_address';

	const string EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_exchange_rate_at_time_of_purchase';

	const string ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_total_at_time_of_purchase';

	/**
	 * @see Bitcoin_Address_WP_Post_Interface::CONFIRMED_AMOUNT_RECEIVED_META_KEY
	 */
	const string BITCOIN_AMOUNT_CONFIRMED_RECEIVED_META_KEY = 'bh_wp_bitcoin_gateway_bitcoin_confirmed_amount_received';

	/**
	 * To parse JSON to typed objects.
	 */
	protected ?JsonMapperInterface $json_mapper = null;

	/**
	 * The payment address does not exist the moment the order is created, and may not exist if data is partially deleted.
	 */
	protected ?Bitcoin_Address $payment_address = null;

	/** @var array<int, Bitcoin_Transaction> */
	protected ?array $transactions = null;

	/**
	 * Used to parse metadata to typed objects.
	 *
	 * @param JsonMapperInterface $json_mapper A json mapper configured to parse `DateTimeInterface` and `Money`.
	 */
	public function set_json_mapper( JsonMapperInterface $json_mapper ): void {
		$this->json_mapper = $json_mapper;
	}

	/**
	 * Set the typed payment address and transactions.
	 *
	 * @param Bitcoin_Wallet_Service $wallet_service Class for querying wallets, addresses.
	 * @param Payment_Service        $payment_service Class for querying locally saved transactions/payments and remote blockchain APIs.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the saved address is not found.
	 */
	public function hydrate(
		Bitcoin_Wallet_Service $wallet_service,
		Payment_Service $payment_service,
	): void {

		if ( $this->get_raw_payment_address() ) {
			$this->payment_address = $wallet_service->get_saved_address_by_bitcoin_payment_address( $this->get_raw_payment_address() );
		}

		$transaction_ids = $this->payment_address?->get_tx_ids();

		if ( ! is_null( $transaction_ids ) ) {
			$this->transactions = $payment_service->get_saved_transactions(
				transaction_post_ids: array_keys( $transaction_ids )
			);
		}
	}

	/**
	 * Save the payment address string to order meta.
	 *
	 * @param Bitcoin_Address $payment_address The address object to read the address string from.
	 * @param bool            $save_now Should `WC_Order::save()` be called immediately.
	 */
	public function set_raw_address( Bitcoin_Address $payment_address, bool $save_now = true ): void {
		$this->add_meta_data(
			self::BITCOIN_ADDRESS_META_KEY,
			$payment_address->get_raw_address(),
			true
		);
		if ( $save_now ) {
			$this->save();
		}
	}

	/**
	 * Get the saved payment address, if present.
	 */
	public function get_raw_payment_address(): ?string {
		$payment_address_meta = $this->get_meta( self::BITCOIN_ADDRESS_META_KEY );
		return is_string( $payment_address_meta ) ? $payment_address_meta : null;
	}

	/**
	 * Save the order value in BTC. (Record the amount the customer has been asked to pay in BTC.).
	 *
	 * Saves as JSON string, e.g. `{"amount":"0.0000543","currency":"BTC"}`.
	 *
	 * @param Money $btc_total The order total in BTC.
	 * @param bool  $save_now Should `WC_Order::save()` be called immediately.
	 * @see Bitcoin_Address::$target_amount
	 */
	public function set_btc_total_price( Money $btc_total, bool $save_now = true ): void {
		$money_json_string = wp_json_encode( $btc_total->jsonSerialize() );
		$this->add_meta_data(
			self::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY,
			$money_json_string,
			true
		);
		if ( $save_now ) {
			$this->save();
		}
	}

	/**
	 * The order price in Bitcoin at the time of purchase.
	 *
	 * @return ?Money The order total in BTC.
	 */
	public function get_btc_total_price(): ?Money {
		$btc_total_meta_string = $this->get_meta( self::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY );
		if ( ! is_string( $btc_total_meta_string ) ) {
			return null;
		}
		try {
			return $this->json_mapper?->mapToClassFromString(
				$btc_total_meta_string,
				Money::class
			);
		} catch ( Exception ) {
			return null;
		}
	}

	/**
	 * Save the exchange rate used to calculate the expected payment amount.
	 *
	 * @param Money $exchange_rate 1 BTC = x.
	 * @param bool  $save_now Should `WC_Order::save()` be called immediately.
	 */
	public function set_exchange_rate( Money $exchange_rate, bool $save_now = true ): void {
		$exchange_rate_json_string = wp_json_encode( $exchange_rate->jsonSerialize() );
		$this->add_meta_data(
			self::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY,
			$exchange_rate_json_string,
			true
		);
		if ( $save_now ) {
			$this->save();
		}
	}

	/**
	 * Get the Bitcoin to fiat exchange rate for this order.
	 * The price of 1 Bitcoin at the time of purchase.
	 *
	 * @return ?Money The exchange rate.
	 */
	public function get_exchange_rate(): ?Money {
		$exchange_rate_meta_json_string = $this->get_meta( self::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY );
		if ( ! is_string( $exchange_rate_meta_json_string ) ) {
			return null;
		}
		try {
			return $this->json_mapper?->mapToClassFromString(
				$exchange_rate_meta_json_string,
				Money::class
			);
		} catch ( Exception ) {
			return null;
		}
	}

	/**
	 * Set the confirmed Bitcoin amount received for this order.
	 *
	 * @param Money $updated_confirmed_value The confirmed amount received in Bitcoin.
	 * @param bool  $save_now Should `WC_Order::save()` be called immediately.
	 */
	public function set_confirmed_amount_received(
		Money $updated_confirmed_value,
		bool $save_now = true
	): void {
		$updated_confirmed_value_json_string = wp_json_encode( $updated_confirmed_value->jsonSerialize() );
		$this->add_meta_data(
			self::BITCOIN_AMOUNT_CONFIRMED_RECEIVED_META_KEY,
			$updated_confirmed_value_json_string,
			true
		);
		if ( $save_now ) {
			$this->save();
		}
	}

	/**
	 * Get the total value with the required number of confirmations at the last checked time.
	 */
	public function get_confirmed_amount_received(): ?Money {
		$confirmed_amount_meta_string = $this->get_meta( self::BITCOIN_AMOUNT_CONFIRMED_RECEIVED_META_KEY );
		if ( ! is_string( $confirmed_amount_meta_string ) ) {
			return null;
		}
		try {
			return $this->json_mapper?->mapToClassFromString(
				$confirmed_amount_meta_string,
				Money::class
			);
		} catch ( Exception ) {
			return null;
		}
	}

	/**
	 * Get the address object.
	 *
	 * I.e. with derivation path, target amount, wallet post id etc.
	 */
	public function get_bitcoin_address(): ?Bitcoin_Address {

		return $this->payment_address;
	}

	/**
	 * Get the last time the order's address was checked for payments.
	 *
	 * TODO: This uses the payment address last_modified which isn't strictly the same thing.
	 */
	public function get_last_checked_time(): ?DateTimeInterface {
		if ( is_null( $this->payment_address ) || is_null( $this->payment_address->get_tx_ids() ) ) {
			return null;
		}
		return $this->payment_address->get_modified_time();
	}

	/**
	 * Get the WC_Payment_Gateway / Bitcoin_Gateway instance.
	 */
	public function get_gateway(): ?Bitcoin_Gateway {

		$gateways   = WC_Payment_Gateways::instance()->payment_gateways;
		$gateway_id = $this->get_payment_method();

		/** @phpstan-ignore-next-line return.type (WordPress polyfill is not templated) */
		return array_first(
			array_filter(
				$gateways,
				fn ( WC_Payment_Gateway $gateway ) => $gateway->id === $gateway_id && $gateway instanceof Bitcoin_Gateway
			)
		);
	}
}
