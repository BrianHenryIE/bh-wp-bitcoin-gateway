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
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Bitcoin_Gateway;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Order;
use DateTimeInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateways;

/**
 * @mixin WC_Order
 */
class WC_Bitcoin_Order implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * The Bitcoin payment gateway used for this order.
	 *
	 * @var Bitcoin_Gateway|null
	 */
	protected ?Bitcoin_Gateway $gateway;

	/**
	 * The number of confirmations the order needs for transactions.
	 *
	 * TODO: Different orders may have different number of confirmations. E.g. more expensive orders might want higher
	 * number of confirmations, orders that need near-er instant completion might trust mempool.
	 *
	 * @var int
	 */
	protected int $confirmations;

	/**
	 * Magic method to proxy method calls to the underlying WooCommerce order.
	 *
	 * @param string       $name The method name to call.
	 * @param array<mixed> $arguments The arguments to pass to the method.
	 *
	 * @throws BadMethodCallException When the method doesn't exist on the underlying WC_Order object.
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
	 * @param WC_Order                      $wc_order The WooCommerce order.
	 * @param Bitcoin_Address               $payment_address The address assigned to the order.
	 * @param ?array<Transaction_Interface> $transactions The known transactions for the address.
	 * @param LoggerInterface               $logger PSR logger.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the order has no Bitcoin address or the address cannot be retrieved.
	 */
	public function __construct(
		protected WC_Order $wc_order,
		protected Bitcoin_Address $payment_address,
		protected ?array $transactions,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Get the Bitcoin payment address associated with this order.
	 */
	public function get_address(): Bitcoin_Address {
		return $this->payment_address;
	}

	/**
	 * A ~proxy for the Bitcoin Address object's last checked time.
	 *
	 * Null when never changed
	 */
	public function get_last_checked_time(): ?DateTimeInterface {
		if ( is_null( $this->payment_address->get_tx_ids() ) ) {
			return null;
		}
		return $this->payment_address->get_modified_time();
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
}
