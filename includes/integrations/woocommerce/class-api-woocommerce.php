<?php
/**
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Scheduler_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\MoneyMismatchException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order_Interface;
use DateInterval;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * Implements API_WooCommerce_Interface
 */
class API_WooCommerce implements API_WooCommerce_Interface, LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param API_Interface                       $api Main plugin API.
	 * @param Bitcoin_Wallet_Service              $wallet_service For creating/fetching wallets and addresses.
	 * @param Payment_Service                     $payment_service For getting transaction data/checking for payments.
	 * @param Background_Jobs_Scheduler_Interface $background_jobs_scheduler When an order is placed, schedule a payment check.
	 * @param LoggerInterface                     $logger PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Bitcoin_Wallet_Service $wallet_service,
		protected Payment_Service $payment_service,
		protected Background_Jobs_Scheduler_Interface $background_jobs_scheduler,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Check a gateway id and determine is it an instance of this gateway type.
	 * Used on thank you page to return early.
	 *
	 * @used-by Thank_You::print_instructions()
	 *
	 * @param string|non-empty-string $gateway_id The id of the gateway to check.
	 */
	public function is_bitcoin_gateway( string $gateway_id ): bool {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! class_exists( WC_Payment_Gateway::class ) ) {
			return false;
		}
		if ( empty( $gateway_id ) ) {
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
		// The second check here is because on the first page load after deleting a plugin, it is still in the active plugins list.
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
	 * @throws BH_WP_Bitcoin_Gateway_Exception When no Bitcoin addresses are available or the address cannot be assigned to the order.
	 */
	public function get_fresh_address_for_order( WC_Order $order, Money $btc_total ): Bitcoin_Address {
		$this->logger->debug( 'Get fresh address for `shop_order:{order_id}`', array( 'order_id' => $order->get_id() ) );

		$btc_address = $this->get_fresh_address_for_gateway( $this->get_bitcoin_gateways()[ $order->get_payment_method() ] );

		if ( is_null( $btc_address ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'No Bitcoin addresses available.' );
		}

		$this->wallet_service->assign_order_to_bitcoin_payment_address(
			address: $btc_address,
			order_id: $order->get_id(),
			btc_total: $btc_total
		);

		$order->add_meta_data( Order::BITCOIN_ADDRESS_META_KEY, $btc_address->get_raw_address() );
		$order->save();

		$this->logger->info(
			'Assigned `bh-bitcoin-address:{post_id}` {address} to `shop_order:{order_id}`.',
			array(
				'post_id'  => $btc_address->get_post_id(),
				'address'  => $btc_address->get_raw_address(),
				'order_id' => $order->get_id(),
			)
		);

		// Now that the address is assigned, schedule a job to check it for payment transactions.
		$this->background_jobs_scheduler->schedule_single_check_assigned_addresses_for_transactions(
			date_time: new DateTimeImmutable( 'now' )->add( new DateInterval( 'PT15M' ) )
		);

		return $btc_address;
	}

	/**
	 * Get an unused payment addresses for a specific payment gateway's wallet.
	 *
	 * TODO: this should Makes a remote API call if the address has not been recently checked.
	 *
	 * @param Bitcoin_Gateway $gateway The Bitcoin payment gateway to get addresses for.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the wallet cannot be created/retrieved or unused addresses cannot be generated.
	 */
	public function get_fresh_address_for_gateway( Bitcoin_Gateway $gateway ): ?Bitcoin_Address {

		if ( empty( $gateway->get_xpub() ) ) {
			$this->logger->debug( "No master public key set on gateway {$gateway->id}", array( 'gateway' => $gateway ) );
			return null;
		}

		$wallet_result = $this->wallet_service->get_or_save_wallet_for_xpub( $gateway->get_xpub() );

		$result = $this->api->ensure_unused_addresses_for_wallet( $wallet_result->wallet, 1 );

		$unused_addresses = $result->get_unused_addresses();

		return empty( $unused_addresses ) ? null : $unused_addresses[ array_key_first( $unused_addresses ) ];
	}

	/**
	 * Check do we have at least one address already generated and ready to use. Does not generate addresses.
	 *
	 * @param Bitcoin_Gateway $gateway The gateway id the address is for.
	 *
	 * @used-by Bitcoin_Gateway::is_available()
	 *
	 * @return bool
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the wallet lookup fails or the address repository cannot be queried.
	 */
	public function is_unused_address_available_for_gateway(Bitcoin_Gateway $gateway ): bool {

		if ( is_null( $gateway->get_xpub() ) ) {
			return false;
		}

		$result           = $this->wallet_service->get_or_save_wallet_for_xpub( $gateway->get_xpub() );
		$unused_addresses = $this->wallet_service->get_unused_bitcoin_addresses( $result->wallet );

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
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the order has no Bitcoin address or blockchain API queries fail during refresh.
	 */
	public function get_order_details( WC_Order $wc_order, bool $refresh = true ): WC_Bitcoin_Order_Interface {

		/** @var ?string $assigned_payment_address */
		$assigned_payment_address = $wc_order->get_meta( Order::BITCOIN_ADDRESS_META_KEY );
		if ( is_null( $assigned_payment_address ) ) {
			// If this were to happen, it should be possible to look up which address is associated with this order id.
			throw new BH_WP_Bitcoin_Gateway_Exception( 'No Bitcoin payment address found for order.' );
		}
		$bitcoin_address = $this->wallet_service->get_saved_address_by_bitcoin_payment_address( $assigned_payment_address );

		$transaction_ids = $bitcoin_address->get_tx_ids();

		$transactions = null;
		if ( ! is_null( $transaction_ids ) ) {
			$transactions = $this->payment_service->get_saved_transactions(
				transaction_post_ids: array_keys( $transaction_ids )
			);
		}

		$bitcoin_order = new WC_Bitcoin_Order(
			wc_order: $wc_order,
			payment_address: $bitcoin_address,
			transactions: $transactions,
			logger: $this->logger
		);

		return $refresh
			? $this->refresh_order( $bitcoin_order )
			: $bitcoin_order;
	}

	/**
	 * Perform a remote check for transactions and save new details to the order.
	 *
	 * TODO: mempool.
	 *
	 * @param WC_Bitcoin_Order_Interface $bitcoin_order The WC_Order order to refresh.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When blockchain API queries fail or transaction data cannot be updated.
	 * @throws MoneyMismatchException If somehow we attempt to perform calculations between two different currencies.
	 * @throws DateMalformedStringException If the saved transaction data has been modified in the db and cannot be deserialized.
	 */
	protected function refresh_order( WC_Bitcoin_Order_Interface $bitcoin_order ): WC_Bitcoin_Order_Interface {

		$updated = false;

		$time_now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$bitcoin_address         = $bitcoin_order->get_address();
		$confirmed_value_current = $bitcoin_address->get_amount_received();

		$check_address_for_payment_result = $this->api->check_address_for_payment( $bitcoin_address );

		// Filter to transactions that have just been seen, so we can record them in notes.
		$new_order_transactions = $check_address_for_payment_result->get_new_transactions();

		$transaction_formatter = new Transaction_Formatter();

		// Add a note saying "one new transactions seen, unconfirmed total =, confirmed total = ...".
		$note = '';
		if ( $check_address_for_payment_result->is_updated() ) {
			$updated = true;
			$note   .= $transaction_formatter->get_order_note( $check_address_for_payment_result->get_new_transactions() );
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
			// TODO: log / exception.
			return $bitcoin_order;
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
				$last_transaction = array_last( $check_address_for_payment_result->all_transactions );
				$bitcoin_order->payment_complete( $last_transaction->get_txid() );
				$this->logger->info( '`shop_order:{order_id}` has been marked paid.', array( 'order_id' => $bitcoin_order->get_id() ) );

				$updated = true;
			}
		}

		if ( $updated && ! is_null( $confirmed_value_current ) ) {
			$bitcoin_order->set_amount_received( $confirmed_value_current );
		}
		$bitcoin_order->set_last_checked_time( $time_now );

		$bitcoin_order->save();

		$refreshed_address = $this->wallet_service->refresh_address( $bitcoin_order->get_address() );
		/** @var WC_Order $refreshed_wc_order */
		$refreshed_wc_order = wc_get_order( $bitcoin_order->get_id() );

		return new WC_Bitcoin_Order(
			wc_order: $refreshed_wc_order,
			payment_address: $refreshed_address,
			transactions: $check_address_for_payment_result->all_transactions,
			logger: $this->logger
		);
	}

	/**
	 * Get order details for printing in HTML templates.
	 *
	 * Returns an array of:
	 * * HTML formatted values
	 * * raw values that are known to be used in the templates
	 * * objects the values are from
	 *
	 * @param WC_Order $order The WooCommerce order object to update.
	 * @param bool     $refresh Should saved order details be returned or remote APIs be queried.
	 *
	 * @uses API_WooCommerce_Interface::get_order_details()
	 * @see  Details_Formatter
	 *
	 * @return array<string, mixed>
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When order details cannot be retrieved or formatted due to missing address or API failures.
	 */
	public function get_formatted_order_details( WC_Order $order, bool $refresh = true ): array {

		$order_details = $this->get_order_details( $order, $refresh );

		$formatted = new Details_Formatter( $order_details );

		// HTML formatted data.
		$result = $formatted->to_array();

		// Raw data. TODO: convert `::get_btc_total_price(): Money`, use typed class with all strings.
		$result['btc_total']           = $order_details->get_btc_total_price();
		$result['btc_exchange_rate']   = $order_details->get_btc_exchange_rate();
		$result['btc_address']         = $order_details->get_address()->get_raw_address();
		$result['transactions']        = $this->api->get_saved_transactions( $order_details->get_address() );
		$result['btc_amount_received'] = $order_details->get_address()->get_amount_received() ?? 'unknown';

		// Objects.
		$result['order']         = $order;
		$result['bitcoin_order'] = $order_details;

		return $result;
	}
}
