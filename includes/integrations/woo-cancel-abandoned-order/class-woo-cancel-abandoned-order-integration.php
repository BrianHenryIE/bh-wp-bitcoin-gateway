<?php
/**
 * Add support for plugin: WooCommerce Cancel Abandoned Order.
 *
 * * Enables the options for Bitcoin gateways
 * * Prevents partially paid orders from being canceled
 *
 * @see https://github.com/rvola/woo-cancel-abandoned-order
 * @see https://wordpress.org/plugins/woo-cancel-abandoned-order/
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order;

use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\Container as DI52_Container;
use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\ContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Adds additional settings to automatically cancel on-hold orders at
 * `wp-admin/admin.php?page=wc-settings&tab=checkout&section=bitcoin_gateway`.
 */
class Woo_Cancel_Abandoned_Order_Integration {

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface&DI52_Container $container PSR container.
	 * @throws ContainerException E.g. if the bound class cannot be instantiated.
	 */
	public function __construct(
		protected DI52_Container $container,
	) {
		$this->container->bind( API_WooCommerce_Interface::class, API_WooCommerce::class );
	}

	/**
	 * Add filters to enable support for WooCommerce Cancel Abandoned Order plugin.
	 *
	 * @throws NotFoundExceptionInterface If the class cannot be resolved (often when an interface does not have a bound class).
	 * @throws ContainerException Other problems when instantiating the requested class.
	 * @throws ContainerExceptionInterface PSR interface for all container exceptions.
	 */
	public function register_hooks(): void {

		/** @var Woo_Cancel_Abandoned_Order $woo_cancel_abandoned_order */
		$woo_cancel_abandoned_order = $this->container->get( Woo_Cancel_Abandoned_Order::class );

		add_filter( 'woo_cao_gateways', array( $woo_cancel_abandoned_order, 'enable_cao_for_bitcoin' ) );
		add_filter( 'woo_cao_before_cancel_order', array( $woo_cancel_abandoned_order, 'abort_canceling_partially_paid_order' ), 10, 3 );
	}
}
