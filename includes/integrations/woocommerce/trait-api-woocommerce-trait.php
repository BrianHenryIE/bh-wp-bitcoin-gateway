<?php
/**
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Status;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order_Interface;
use DateInterval;
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

		$this->bitcoin_address_repository->assign_to_order(
			address: $btc_address,
			order_id: $order->get_id(),
			btc_total: $btc_total
		);

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

		// Now that the address is assigned, schedule a job to check it for payment transactions.
		$this->background_jobs_scheduler->schedule_single_check_assigned_addresses_for_transactions(
			date_time: new DateTimeImmutable( 'now' )->add( new DateInterval( 'PT15M' ) )
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

		$wallet = $this->bitcoin_wallet_repository->get_by_xpub( $gateway->get_xpub() )
							?? $this->bitcoin_wallet_repository->save_new( $gateway->get_xpub(), $gateway->id );

		$this->ensure_unused_addresses_for_wallet( $wallet, 1 );

		return $this->bitcoin_address_repository->get_addresses(
			wallet: $wallet,
			status: Bitcoin_Address_Status::UNUSED,
		);
	}

	/**
	 * Check do we have at least one address already generated and ready to use. Does not generate addresses.
	 *
	 * @param Bitcoin_Gateway $gateway The gateway id the address is for.
	 *
	 * @used-by Bitcoin_Gateway::is_available()
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function is_fresh_address_available_for_gateway( Bitcoin_Gateway $gateway ): bool {

		$wallet           = $this->bitcoin_wallet_repository->get_by_xpub( $gateway->get_xpub() );
		$unused_addresses = $this->bitcoin_address_repository->get_unused_bitcoin_addresses( $wallet );

		// TODO: maybe schedule a job to find an unused address.

		return count( $unused_addresses ) > 0;
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

		$bitcoin_address = $bitcoin_order->get_address();

		$update_address_transactions_result = $this->update_address_transactions( $bitcoin_address );

		$confirmed_value_current = $bitcoin_order->get_address()->get_amount_received();

		// Filter to transactions that have just been seen, so we can record them in notes.
		$new_order_transactions = $update_address_transactions_result->get_new_transactions();

		$transaction_formatter = new Transaction_Formatter();

		// Add a note saying "one new transactions seen, unconfirmed total =, confirmed total = ...".
		$note = '';
		if ( ! empty( $update_address_transactions_result->get_new_transactions() ) ) {
			$updated = true;
			$note   .= $transaction_formatter->get_order_note( $update_address_transactions_result->get_new_transactions() );
		}

		if ( ! empty( $note ) ) {
			$this->logger->info(
				$note,
				array(
					'order_id' => $bitcoin_order->get_id(),
					'updates'  => $new_order_transactions,
				)
			);

			$bitcoin_order->add_order_note( $note );
		}

		$gateway = $bitcoin_order->get_gateway();

		if ( ! $gateway ) {
			return false;
		}

		if ( ! $bitcoin_order->is_paid() && ! is_null( $confirmed_value_current ) && ! $confirmed_value_current->isZero() ) {
			$expected        = $bitcoin_order->get_btc_total_price();
			$price_margin    = $gateway->get_price_margin_percent();
			$minimum_payment = $expected->multipliedBy( ( 100 - $price_margin ) / 100 );

			if ( $confirmed_value_current->isGreaterThan( $minimum_payment ) ) {
				/**
				 * We know there must be at least one transaction if we've summed them to the required amount!
				 *
				 * @var Transaction_Interface $last_transaction
				 */
				$last_transaction = array_last( $update_address_transactions_result->all_transactions );
				$bitcoin_order->payment_complete( $last_transaction->get_txid() );
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
		$result['transactions']        = $this->get_saved_transactions( $order_details->get_address() );
		$result['btc_amount_received'] = $order_details->get_address()->get_amount_received() ?? 'unknown';

		// Objects.
		$result['order']         = $order;
		$result['bitcoin_order'] = $order_details;

		return $result;
	}
}
