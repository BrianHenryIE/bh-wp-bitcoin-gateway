<?php
/**
 * Add an admin submenu of the WooCommerce "Payments" menu item, linking to the Bitcoin gateway.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @see \Automattic\WooCommerce\Internal\Admin\Settings\PaymentsController::add_menu()
 */
class Menu {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param API_WooCommerce_Interface $api_woocommerce To get the list of Bitcoin gateways and their ids.
	 * @param LoggerInterface           $logger A PSR logger.
	 */
	public function __construct(
		protected API_WooCommerce_Interface $api_woocommerce,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * @hooked admin_menu
	 */
	public function add_woocommerce_payments_submenu(): void {

		try {
			$bitcoin_gateways = $this->api_woocommerce->get_bitcoin_gateways();
		} catch ( Throwable $throwable ) {
			// `admin_menu` runs on every wp-admin page; a gateway failure must not break the whole admin.
			$this->logger->error(
				'Could not add Bitcoin gateway submenu: ' . $throwable->getMessage(),
				array( 'exception' => $throwable )
			);
			return;
		}

		foreach ( $bitcoin_gateways as $bitcoin_gateway ) {
			$id_in_title = count( $bitcoin_gateways ) === 1 ? '' : " ({$bitcoin_gateway->id})";
			add_submenu_page(
				'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM',
				'Bitcoin Gateway',
				'Bitcoin' . $id_in_title,
				'manage_woocommerce',
				'admin.php?page=wc-settings&tab=checkout&section=' . $bitcoin_gateway->id
			);
		}
	}
}
