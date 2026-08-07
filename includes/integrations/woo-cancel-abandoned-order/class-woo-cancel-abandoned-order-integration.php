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
	 * @param ContainerInterface $container PSR container.
	 */
	public function __construct(
		protected ContainerInterface $container,
	) {
	}

	/**
	 * Add filters to enable support for WooCommerce Cancel Abandoned Order plugin.
	 *
	 * @throws NotFoundExceptionInterface If the id being resolved (or one of its dependencies) is not found in the container.
	 * @throws ContainerExceptionInterface If the container fails to instantiate a resolved class.
	 */
	public function register_hooks(): void {

		/** @var Woo_Cancel_Abandoned_Order $woo_cancel_abandoned_order */
		$woo_cancel_abandoned_order = $this->container->get( Woo_Cancel_Abandoned_Order::class );

		add_filter( 'woo_cao_gateways', array( $woo_cancel_abandoned_order, 'enable_cao_for_bitcoin' ) );
		add_filter( 'woo_cao_before_cancel_order', array( $woo_cancel_abandoned_order, 'abort_canceling_partially_paid_order' ), 10, 3 );
	}
}
