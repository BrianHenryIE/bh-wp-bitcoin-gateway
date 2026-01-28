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
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Check_Address_For_Payment_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\MoneyMismatchException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Helpers\WC_Order_Meta_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use DateInterval;
use DateMalformedStringException;
use DateTimeImmutable;
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
	 * @param WC_Order_Meta_Helper                $order_meta_helper Meta helper.
	 * @param Background_Jobs_Scheduler_Interface $background_jobs_scheduler When an order is placed, schedule a payment check.
	 * @param LoggerInterface                     $logger PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Bitcoin_Wallet_Service $wallet_service,
		protected Payment_Service $payment_service,
		protected WC_Order_Meta_Helper $order_meta_helper,
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
	public function assign_unused_address_to_order( WC_Order $order, Money $btc_total ): Bitcoin_Address {
		$this->logger->debug( 'Get fresh address for `shop_order:{order_id}`', array( 'order_id' => $order->get_id() ) );

		$btc_address = $this->get_fresh_address_for_gateway( $this->get_bitcoin_gateways()[ $order->get_payment_method() ] );

		if ( is_null( $btc_address ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'No Bitcoin addresses available.' );
		}

		$refreshed_address = $this->wallet_service->assign_order_to_bitcoin_payment_address(
			address: $btc_address,
			integration_id: WooCommerce_Integration::class,
			order_id: $order->get_id(),
			btc_total: $btc_total
		);

		$this->order_meta_helper->set_raw_address( $order, $btc_address );

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

		return $refreshed_address;
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

		$result = $this->api->ensure_unused_addresses_for_wallet_synchronously( $wallet_result->wallet, 1 );

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
	public function is_unused_address_available_for_gateway( Bitcoin_Gateway $gateway ): bool {

		if ( is_null( $gateway->get_xpub() ) ) {
			return false;
		}

		$get_wallet_for_xpub_service_result = $this->wallet_service->get_or_save_wallet_for_xpub( $gateway->get_xpub() );

		// This will schedule a job if there are none.
		return $this->api->is_unused_address_available_for_wallet( $get_wallet_for_xpub_service_result->wallet );
	}

	/**
	 * Get the current status of the order's payment.
	 *
	 * As a really detailed array for printing.
	 *
	 * `array{btc_address:string, bitcoin_total:Money, btc_price_at_at_order_time:string, transactions:array<string, TransactionArray>, btc_exchange_rate:string, last_checked_time:DateTimeInterface, btc_amount_received:string, order_status_before:string}`
	 *
	 * @param WC_Order $wc_order The WooCommerce order to check.
	 *
	 * @return WC_Bitcoin_Order
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the order has no Bitcoin address or blockchain API queries fail during refresh.
	 */
	public function get_order_details( WC_Order $wc_order ): WC_Bitcoin_Order {

		/** @var ?string $assigned_payment_address */
		$assigned_payment_address = $this->order_meta_helper->get_raw_payment_address( $wc_order );

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

		return $bitcoin_order;
	}

	/**
	 * Perform a remote check for transactions and save new details to the order.
	 *
	 * TODO: mempool.
	 *
	 * @param WC_Order $order The WC_Order order to refresh.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When blockchain API queries fail or transaction data cannot be updated.
	 * @throws MoneyMismatchException If somehow we attempt to perform calculations between two different currencies.
	 * @throws DateMalformedStringException If the saved transaction data has been modified in the db and cannot be deserialized.
	 */
	public function check_order_for_payment( WC_Order $order ): void {

		$bitcoin_order = $this->get_order_details( $order );

		$bitcoin_address        = $bitcoin_order->get_address();
		$confirmed_value_before = $bitcoin_address->get_amount_received();

		$check_address_for_payment_result = $this->api->check_address_for_payment( $bitcoin_address );

		/**
		 * By this point 0/1/both {@see Order::new_transactions_seen()} or {@see Order::payment_received()} will have
		 * been called.
		 *
		 * @see self::add_order_note_for_transactions()
		 * @see self::mark_order_paid()
		 *
		 * Maybe `remove_action` before the user initiated synchronous call so better data can be returned?
		 *
		 * `remove_all_actions('bh_wp_bitcoin_gateway_new_transactions_seen')`.
		 */
	}

	/**
	 * Mark the order as paid using the latest transaction's id as the order transaction id. Save the amount
	 * received to the order meta.
	 *
	 * @see WC_Order::payment_complete()
	 *
	 * @param WC_Order                                 $wc_order The order in question.
	 * @param Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result The details of the requirements + transactions.
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the amount is invalid.
	 */
	public function mark_order_paid(
		WC_Order $wc_order,
		Check_Address_For_Payment_Service_Result $check_address_for_payment_service_result,
	): void {

		if ( $check_address_for_payment_service_result->confirmed_received->isNegativeOrZero() ) {
			// This should never happen.
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Invalid amount_received: ' . $check_address_for_payment_service_result->confirmed_received->__toString() . ' is negative or zero.' );
		}

		$this->order_meta_helper->set_confirmed_amount_received( $wc_order, $check_address_for_payment_service_result->confirmed_received );

		/**
		 * We know there must be at least one transaction if we've summed them to the required amount!
		 *
		 * @var Transaction_Interface $last_transaction
		 */
		$last_transaction = array_last( $check_address_for_payment_service_result->all_transactions );
		$wc_order->payment_complete( $last_transaction->get_txid() );
		$wc_order->save();

		$this->logger->info( '`shop_order:{order_id}` has been marked paid.', array( 'order_id' => $wc_order->get_id() ) );
	}

	/**
	 * Add a note saying "New transactions seen", linking to the details.
	 *
	 * TODO: show ~"unconfirmed total =..., confirmed total = ...".
	 *
	 * @deprecated This function signature is expected to change to pass data for totals. Please don't use it directly.
	 *
	 * @used-by Order::new_transactions_seen()
	 * @used-by self::refresh_order()
	 *
	 * @param WC_Order                     $order The WooCommerce order to record the new transactions for.
	 * @param array<Transaction_Interface> $new_transactions The transactions.
	 */
	public function add_order_note_for_transactions( WC_Order $order, array $new_transactions ): void {
		$note = Transaction_Formatter::get_order_note( $new_transactions );
		$order->add_order_note( $note );
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
	 *
	 * @uses API_WooCommerce_Interface::get_order_details()
	 * @see  Details_Formatter
	 *
	 * @return array<string, mixed>
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When order details cannot be retrieved or formatted due to missing address or API failures.
	 */
	public function get_formatted_order_details( WC_Order $order ): array {

		$order_details = $this->get_order_details( $order );

		$formatted = new Details_Formatter( $order_details, $this->order_meta_helper );

		// HTML formatted data.
		$result = $formatted->to_array();

		// Raw data. TODO: convert `::get_btc_total_price(): Money`, use typed class with all strings.
		$result['btc_total']           = $this->order_meta_helper->get_btc_total_price( $order );
		$result['btc_exchange_rate']   = $this->order_meta_helper->get_exchange_rate( $order );
		$result['btc_address']         = $order_details->get_address()->get_raw_address();
		$result['transactions']        = $this->api->get_saved_transactions( $order_details->get_address() );
		$result['btc_amount_received'] = $order_details->get_address()->get_amount_received() ?? 'unknown';

		// Objects.
		$result['order']         = $order;
		$result['bitcoin_order'] = $order_details;

		return $result;
	}
}
