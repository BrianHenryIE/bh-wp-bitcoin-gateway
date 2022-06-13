<?php
/**
 * Add a metabox with the payment details on the admin order page.
 *
 * @package    nullcorps/woocommerce-gateway-bitcoin
 */

namespace Nullcorps\WC_Gateway_Bitcoin\WooCommerce;

use DateTime;
use Nullcorps\WC_Gateway_Bitcoin\API\API_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;
use WP_Post;

/**
 * Register and print a metabox on the shop_order page, display it only when the order is a Bitcoin order.
 */
class Admin_Order_UI {
	use LoggerAwareTrait;

	/**
	 * Instance of the mail plugin class.
	 *
	 * @var API_Interface
	 */
	protected API_Interface $api;

	/**
	 * Constructor
	 *
	 * @param API_Interface   $api Required for order details.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct( API_Interface $api, LoggerInterface $logger ) {
		$this->setLogger( $logger );
		$this->api = $api;
	}

	/**
	 * Register the Bitcoin order details metabox on shop_order admin edit view.
	 *
	 * @hooked add_meta_boxes
	 *
	 * @return void
	 */
	public function register_address_transactions_meta_box(): void {

		global $post;

		$order_id = $post->ID;

		if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			return;
		}

		add_meta_box(
			'nullcorps-wc-gateway-bitcoin',
			'Bitcoin',
			array( $this, 'print_address_transactions_metabox' ),
			'shop_order',
			'normal',
			'core'
		);
	}

	/**
	 * Print a box of information showing the Bitcoin address, amount expcted, paid, transactions, last checked date.
	 *
	 * TODO: Display the difference between amount required and amount paid?
	 * TODO: "Check now" button.
	 *
	 * @see Admin_Order_UI::register_address_transactions_meta_box();
	 *
	 * @param WP_Post $post The post this edit page is running for.
	 */
	public function print_address_transactions_metabox( WP_Post $post ): void {

		$order_id = $post->ID;

		if ( ! $this->api->is_order_has_bitcoin_gateway( $order_id ) ) {
			return;
		}

		/**
		 * This is almost sure to be a valid order object, since this only runs on the order page.
		 *
		 * @var WC_Order $order
		 */
		$order = wc_get_order( $order_id );

		$order_details = $this->api->get_formatted_order_details( $order );

		echo '<table>';

		echo '<tr><td>Order Total:</td><td>' . esc_html( $order_details['btc_total_formatted'] ) . '</td></tr>';

		// Add a link to showing the exchange rate around the time of the order ( -12 hours to +12 hours after payment).

		/**
		 * This supposedly could be null, but I can't imagine a scenario where WooCommerce returns an order object
		 * that doesn't have a DateTime for created.
		 *
		 * @var DateTime $date_created
		 */
		$date_created = $order->get_date_created();
		$from         = $date_created->getTimestamp() - ( DAY_IN_SECONDS / 2 );
		if ( ! is_null( $order->get_date_paid() ) ) {
			$to = $order->get_date_paid()->getTimestamp() + ( DAY_IN_SECONDS / 2 );
		} else {
			$to = $from + DAY_IN_SECONDS;
		}
		echo '<tr>';
		echo '<td>Exchange Rate:</td>';
		echo '<td><a target="_blank" href="' . esc_url( "https://www.blockchain.com/prices/BTC?from={$from}&to={$to}&timeSpan=custom&scale=0&style=line" ) . '">' . esc_html( $order_details['btc_exchange_rate'] ) . '</a></td>';
		echo '</tr>';

		$btc_address = $order_details['btc_address'];
		echo '<tr>';
		echo '<td>Payment Address:</td>';
		echo '<td><a target="_blank" href="' . esc_url( "https://www.blockchain.com/btc/address/{$btc_address}" ) . '">' . esc_html( $btc_address ) . '</a></td>';
		echo '</tr>';

		$transactions = $order_details['transactions'];
		echo '<tr>';
		echo '<td>Transactions:</td>';
		echo '<td>';
		if ( empty( $transactions ) ) {
			echo esc_html__( 'No transactions yet', 'nullcorps-wc-gateway-bitcoin' );
		} else {
			echo 'todo';
		}
		echo '</td>';
		echo '</tr>';

		echo '<tr><td>Amount received:</td><td>' . esc_html( $order_details['btc_amount_received_formatted'] ) . '</td></tr>';

		echo '<tr><td>Last Checked:</td><td>' . esc_html( $order_details['last_checked_time_formatted'] ) . '</td></tr>';

		echo '</table>';
	}

}
