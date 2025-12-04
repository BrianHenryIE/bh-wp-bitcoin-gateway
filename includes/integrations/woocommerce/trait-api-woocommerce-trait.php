<?php
/**
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order_Interface;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * implements API_WooCommerce_Interface
 */
trait API_WooCommerce_Trait {

	/**
	 * Check a gateway id and determine is it an instance of this gateway type.
	 * Used on thank you page to return early.
	 *
	 * @used-by Thank_You::print_instructions()
	 *
	 * @param string $gateway_id The id of the gateway to check.
	 */
	public function is_bitcoin_gateway( string $gateway_id ): bool {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! class_exists( WC_Payment_Gateway::class ) ) {
			return false;
		}

		$bitcoin_gateways = $this->get_bitcoin_gateways();

		$gateway_ids = array_map(
			function ( WC_Payment_Gateway $gateway ): string {
				return $gateway->id;
			},
			$bitcoin_gateways
		);

		return in_array( $gateway_id, $gateway_ids, true );
	}

	/**
	 * Get all instances of the Bitcoin gateway.
	 * (typically there is only one).
	 *
	 * @return array<string, Bitcoin_Gateway>
	 */
	public function get_bitcoin_gateways(): array {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! class_exists( WC_Payment_Gateways::class ) ) {
			return array();
		}

		$payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();
		$bitcoin_gateways = array();
		foreach ( $payment_gateways as $gateway ) {
			if ( $gateway instanceof Bitcoin_Gateway ) {
				$bitcoin_gateways[ $gateway->id ] = $gateway;
			}
		}

		return $bitcoin_gateways;
	}

	/**
	 * Given an order id, determine is the order's gateway an instance of this Bitcoin gateway.
	 *
	 * @see https://github.com/BrianHenryIE/bh-wp-duplicate-payment-gateways
	 *
	 * @param int|string $order_id The id of the (presumed) WooCommerce order to check.
	 */
	public function is_order_has_bitcoin_gateway( int|string $order_id ): bool {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			// Unlikely.
			return false;
		}

		$payment_gateway_id = $order->get_payment_method();

		if ( ! $this->is_bitcoin_gateway( $payment_gateway_id ) ) {
			// Exit, this isn't for us.
			return false;
		}

		return true;
	}

	/**
	 * Fetches an unused address from the cache, or generates a new one if none are available.
	 *
	 * Called inside the "place order" function, then it can throw an exception.
	 * if there's a problem and the user can immediately choose another payment method.
	 *
	 * Load our already generated fresh list.
	 * Check with a remote API that it has not been used.
	 * Save it to the order metadata.
	 * Save it locally as used.
	 * Maybe schedule more address generation.
	 * Return it to be used in an order.
	 *
	 * @used-by Bitcoin_Gateway::process_payment()
	 *
	 * @param WC_Order $order The order that will use the address.
	 * @param Money    $btc_total The required value of Bitcoin after which this order will be considered paid.
	 *
	 * @return Bitcoin_Address
	 * @throws Exception
	 */
	public function get_fresh_address_for_order( WC_Order $order, Money $btc_total ): Bitcoin_Address {
		$this->logger->debug( 'Get fresh address for `shop_order:' . $order->get_id() . '`' );

		$btc_addresses = $this->get_fresh_addresses_for_gateway( $this->get_bitcoin_gateways()[ $order->get_payment_method() ] );

		if ( empty( $btc_addresses ) ) {
			throw new Exception( 'No Bitcoin addresses available.' );
		}

		$btc_address = array_shift( $btc_addresses );

		$btc_address->assign( $order->get_id(), $btc_total );

		$order->add_meta_data( Order::BITCOIN_ADDRESS_META_KEY, $btc_address->get_raw_address() );
		$order->save();

		$this->logger->info(
			sprintf(
				'Assigned `bh-bitcoin-address:%d` %s to `shop_order:%d`.',
				$this->bitcoin_address_repository->get_post_id_for_address( $btc_address->get_raw_address() ),
				$btc_address->get_raw_address(),
				$order->get_id()
			)
		);

		return $btc_address;
	}

	/**
	 * @param Bitcoin_Gateway $gateway
	 *
	 * @return Bitcoin_Address[]
	 * @throws Exception
	 */
	public function get_fresh_addresses_for_gateway( Bitcoin_Gateway $gateway ): array {

		if ( empty( $gateway->get_xpub() ) ) {
			$this->logger->debug( "No master public key set on gateway {$gateway->id}", array( 'gateway' => $gateway ) );
			return array();
		}

		$wallet_post_id = $this->bitcoin_wallet_repository->get_post_id_for_wallet( $gateway->get_xpub() )
							?? $this->bitcoin_wallet_repository->save_new( $gateway->get_xpub(), $gateway->id );

		$wallet = $this->bitcoin_wallet_repository->get_by_wp_post_id( $wallet_post_id );

		return $wallet->get_fresh_addresses();
	}

	/**
	 * Check do we have at least one address already generated and ready to use.
	 *
	 * @param Bitcoin_Gateway $gateway The gateway id the address is for.
	 *
	 * @used-by Bitcoin_Gateway::is_available()
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function is_fresh_address_available_for_gateway( Bitcoin_Gateway $gateway ): bool {
		// TODO: cache this.
		return count( $this->get_fresh_addresses_for_gateway( $gateway ) ) > 0;
	}

	/**
	 * Get the current status of the order's payment.
	 *
	 * As a really detailed array for printing.
	 *
	 * `array{btc_address:string, bitcoin_total:Money, btc_price_at_at_order_time:string, transactions:array<string, TransactionArray>, btc_exchange_rate:string, last_checked_time:DateTimeInterface, btc_amount_received:string, order_status_before:string}`
	 *
	 * @param WC_Order $wc_order The WooCommerce order to check.
	 * @param bool     $refresh Should the result be returned from cache or refreshed from remote APIs.
	 *
	 * @return WC_Bitcoin_Order_Interface
	 * @throws Exception
	 */
	public function get_order_details( WC_Order $wc_order, bool $refresh = true ): WC_Bitcoin_Order_Interface {

		$bitcoin_order = new WC_Bitcoin_Order( $wc_order, $this->bitcoin_address_repository );

		if ( $refresh ) {
			$this->refresh_order( $bitcoin_order );
		}

		return $bitcoin_order;
	}

	/**
	 *
	 * TODO: mempool.
	 *
	 * @param WC_Bitcoin_Order_Interface $bitcoin_order
	 *
	 * @throws Exception
	 */
	protected function refresh_order( WC_Bitcoin_Order_Interface $bitcoin_order ): bool {

		$updated = false;

		$time_now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$order_transactions_before = $bitcoin_order->get_address()->get_blockchain_transactions();

		if ( is_null( $order_transactions_before ) ) {
			$this->logger->debug( 'Refresh order: Checking for the first time' );
			$order_transactions_before = array();
		}

		/** @var array<string, Transaction_Interface> $address_transactions_current */
		$address_transactions_current = $this->update_address_transactions( $bitcoin_order->get_address() );

		// TODO: Check are any previous transactions no longer present!!!

		// Filter to transactions that occurred after the order was placed.
		$order_transactions_current = array();
		foreach ( $address_transactions_current as $txid => $transaction ) {
			// TODO: maybe use block height at order creation rather than date?
			// TODO: be careful with timezones.
			if ( $transaction->get_time() > $bitcoin_order->get_date_created() ) {
				$order_transactions_current[ $txid ] = $transaction;
			}
		}

		$order_transactions_current_mempool = array_filter(
			$address_transactions_current,
			function ( Transaction_Interface $transaction ): bool {
				return is_null( $transaction->get_block_height() );
			}
		);

		$order_transactions_current_blockchain = array_filter(
			$address_transactions_current,
			function ( Transaction_Interface $transaction ): bool {
				return ! is_null( $transaction->get_block_height() );
			}
		);

		$gateway = $bitcoin_order->get_gateway();

		if ( ! $gateway ) {
			return false;
		}

		// TODO: allow customising.
		$required_confirmations = 3;

		try {
			$blockchain_height = $this->blockchain_api->get_blockchain_height();
		} catch ( Exception $_e ) {
			// TODO: log, notify, rate limit.
			return false;
		}

		$raw_address = $bitcoin_order->get_address()->get_raw_address();

		$confirmed_value_current = $bitcoin_order->get_address()->get_confirmed_balance( $blockchain_height, $required_confirmations );

		$unconfirmed_value_current = array_reduce(
			$order_transactions_current_blockchain,
			function ( Money $carry, Transaction_Interface $transaction ) use ( $blockchain_height, $required_confirmations, $raw_address ) {
				if ( $blockchain_height - ( $transaction->get_block_height() ?? $blockchain_height ) > $required_confirmations ) {
					return $carry;
				}
				return $carry->plus( $transaction->get_value( $raw_address ) );
			},
			Money::of( 0, 'BTC' )
		);

		// Filter to transactions that have just been seen, so we can record them in notes.
		$new_order_transactions = array();
		foreach ( $order_transactions_current as $txid => $transaction ) {
			if ( ! isset( $order_transactions_before[ $txid ] ) ) {
				$new_order_transactions[ $txid ] = $transaction;
			}
		}

		$transaction_formatter = new Transaction_Formatter();

		// Add a note saying "one new transactions seen, unconfirmed total =, confirmed total = ...".
		$note = '';
		if ( ! empty( $new_order_transactions ) ) {
			$updated = true;
			$note   .= $transaction_formatter->get_order_note( $new_order_transactions );
		}

		if ( ! empty( $note ) ) {
			$this->logger->info(
				$note,
				array(
					'order_id' => $bitcoin_order->get_id(),
					'updates'  => $order_transactions_current,
				)
			);

			$bitcoin_order->add_order_note( $note );
		}

		if ( ! $bitcoin_order->is_paid() && ! is_null( $confirmed_value_current ) && ! $confirmed_value_current->isZero() ) {
			$expected        = $bitcoin_order->get_btc_total_price();
			$price_margin    = $gateway->get_price_margin_percent();
			$minimum_payment = $expected->multipliedBy( ( 100 - $price_margin ) / 100 );

			if ( $confirmed_value_current->isGreaterThan( $minimum_payment ) ) {
				$bitcoin_order->payment_complete( $order_transactions_current[ array_key_last( $order_transactions_current ) ]->get_txid() );
				$this->logger->info( "`shop_order:{$bitcoin_order->get_id()}` has been marked paid.", array( 'order_id' => $bitcoin_order->get_id() ) );

				$updated = true;
			}
		}

		if ( $updated && ! is_null( $confirmed_value_current ) ) {
			$bitcoin_order->set_amount_received( $confirmed_value_current );
		}
		$bitcoin_order->set_last_checked_time( $time_now );

		$bitcoin_order->save();

		return $updated;
	}

	/**
	 * Get order details for printing in HTML templates.
	 *
	 * Returns an array of:
	 * * html formatted values
	 * * raw values that are known to be used in the templates
	 * * objects the values are from
	 *
	 * @param WC_Order $order The WooCommerce order object to update.
	 * @param bool     $refresh Should saved order details be returned or remote APIs be queried.
	 *
	 * @return array<string, mixed>
	 *
	 * @uses \BrianHenryIE\WP_Bitcoin_Gateway\API_Interface::get_order_details()
	 * @see  Details_Formatter
	 *
	 * @throws Exception
	 */
	public function get_formatted_order_details( WC_Order $order, bool $refresh = true ): array {

		$order_details = $this->get_order_details( $order, $refresh );

		$formatted = new Details_Formatter( $order_details );

		// HTML formatted data.
		$result = $formatted->to_array();

		// Raw data.
		$result['btc_total']           = $order_details->get_btc_total_price();
		$result['btc_exchange_rate']   = $order_details->get_btc_exchange_rate();
		$result['btc_address']         = $order_details->get_address()->get_raw_address();
		$result['transactions']        = $order_details->get_address()->get_blockchain_transactions();
		$result['btc_amount_received'] = $order_details->get_address()->get_amount_received() ?? 'unknown';

		// Objects.
		$result['order']         = $order;
		$result['bitcoin_order'] = $order_details;

		return $result;
	}
}
