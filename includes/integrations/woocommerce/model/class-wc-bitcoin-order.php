<?php
/**
 * A ~facade on WC_Order that returns strongly typed data.
 *
 * I.e. to return its string meta address as a typed Bitcoin_Address etc.
 *
 * @package brianehnryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model;

use BadMethodCallException;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\BigNumber;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Order;
use DateTimeInterface;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use WC_Order;
use WC_Payment_Gateways;

/**
 * @mixin WC_Order
 */
class WC_Bitcoin_Order implements WC_Bitcoin_Order_Interface {
	use LoggerAwareTrait;

	protected WC_Order $wc_order;

	protected Bitcoin_Address $address;

	protected ?Bitcoin_Gateway $gateway;

	/**
	 * The number of confirmations the order needs for transactions.
	 *
	 * TODO: Different orders may have different number of confirmations. E.g. more expensive orders might want higher
	 * number of confirmations, orders that need near-er instant completion might trust mempool.
	 */
	protected int $confirmations;
	protected Money $amount_received;
	protected DateTimeInterface $last_checked_time;

	/**
	 * Magic method to proxy method calls to the underlying WooCommerce order.
	 *
	 * @param string       $name The method name to call.
	 * @param array<mixed> $arguments The arguments to pass to the method.
	 */
	public function __call( string $name, array $arguments ): mixed {
		if ( is_callable( array( $this->wc_order, $name ) ) ) {
			return $this->wc_order->$name( ...$arguments );
		}
		throw new BadMethodCallException();
	}

	/**
	 * Constructor.
	 *
	 * @param WC_Order                   $wc_order The WooCommerce order.
	 * @param Bitcoin_Address_Repository $bitcoin_address_repository Repository for Bitcoin addresses.
	 */
	public function __construct(
		WC_Order $wc_order,
		Bitcoin_Address_Repository $bitcoin_address_repository
	) {

		$this->wc_order = $wc_order;

		try {
			/** @var ?string $bitcoin_address_xpub */
			$bitcoin_address_xpub = $wc_order->get_meta( Order::BITCOIN_ADDRESS_META_KEY );
			if ( is_null( $bitcoin_address_xpub ) ) {
				// If this were to happen, it should be possible to look up which address is associated with this order id.
				throw new BH_WP_Bitcoin_Gateway_Exception( 'No Bitcoin address found for order.' );
			}
			$bitcoin_address_post_id = $bitcoin_address_repository->get_post_id_for_address( $bitcoin_address_xpub );
			if ( is_null( $bitcoin_address_post_id ) ) {
				throw new BH_WP_Bitcoin_Gateway_Exception( 'Problem with order Bitcoin address.' );
			}
			$this->address = $bitcoin_address_repository->get_by_post_id( $bitcoin_address_post_id );
		} catch ( Exception $exception ) {
			// $this->logger->warning( "`shop_order:{$order->get_id()}` has no Bitcoin address.", array( 'order_id' => $order->get_id() ) );
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Problem with order Bitcoin address.' );
		}

		$this->setLogger( new NullLogger() );
	}

	/**
	 * The order price in Bitcoin at the time of purchase.
	 */
	public function get_btc_total_price(): Money {
		/** @var array{amount:string, currency:string} $btc_total */
		$btc_total = $this->wc_order->get_meta( Order::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY );

		return Money::of( $btc_total['amount'], $btc_total['currency'] );
	}

	/**
	 * The Bitcoin exchange rate at the time of purchase.
	 */
	public function get_btc_exchange_rate(): BigNumber {
		/** @var array{amount:string, currency:string} $rate_meta */
		$rate_meta = $this->wc_order->get_meta( Order::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY );

		return BigNumber::of( $rate_meta['amount'] );
	}

	/**
	 * Get the Bitcoin payment address associated with this order.
	 */
	public function get_address(): Bitcoin_Address {
		return $this->address;
	}

	/**
	 * A ~proxy for the Bitcoin Address object's last checked time.
	 *
	 * Null when never changed
	 */
	public function get_last_checked_time(): ?DateTimeInterface {
		if ( is_null( $this->address->get_tx_ids() ) ) {
			return null;
		}
		/** @var DateTimeInterface|mixed $last_checked_time */
		$last_checked_time = $this->wc_order->get_meta( Order::LAST_CHECKED_META_KEY );
		return $last_checked_time instanceof DateTimeInterface ? $last_checked_time : null;
	}

	/**
	 * Set the timestamp when the Bitcoin address was last checked for payments.
	 *
	 * @param DateTimeInterface $last_checked_time The timestamp of the last check.
	 */
	public function set_last_checked_time( DateTimeInterface $last_checked_time ): void {
		// @phpstan-ignore-next-line This works fine.
		$this->wc_order->add_meta_data( Order::LAST_CHECKED_META_KEY, $last_checked_time, true );
		// TODO: Save?
		$this->last_checked_time = $last_checked_time;
	}

	/**
	 * Get the order's gateway.
	 *
	 * Since the gateway id could change, particularly where there are multiple instances, it may happen that the id
	 * in the order does not match an existing gateway, => return null.
	 */
	public function get_gateway(): ?Bitcoin_Gateway {
		if ( ! isset( WC_Payment_Gateways::instance()->payment_gateways[ $this->wc_order->get_payment_method() ] ) ) {
			return null;
		}

		if ( ! ( WC_Payment_Gateways::instance()->payment_gateways[ $this->wc_order->get_payment_method() ] instanceof Bitcoin_Gateway ) ) {
			return null;
		}

		return WC_Payment_Gateways::instance()->payment_gateways[ $this->wc_order->get_payment_method() ];
	}

	/**
	 * Get the total value with the required number of confirmations at the last checked time.
	 */
	public function get_amount_received(): Money {
		return $this->amount_received;
	}

	/**
	 * Set the confirmed Bitcoin amount received for this order.
	 *
	 * @param Money $updated_confirmed_value The confirmed amount received in Bitcoin.
	 */
	public function set_amount_received( Money $updated_confirmed_value ): void {
		$this->wc_order->add_meta_data( Order::BITCOIN_AMOUNT_RECEIVED_META_KEY, $updated_confirmed_value, true );
		$this->amount_received = $updated_confirmed_value;
	}
}
