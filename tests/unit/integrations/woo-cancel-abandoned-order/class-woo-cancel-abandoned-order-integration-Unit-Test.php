<?php
/**
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order;

use BrianHenryIE\WP_Bitcoin_Gateway\lucatume\DI52\Container as DI52_Container;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order\Woo_Cancel_Abandoned_Order_Integration
 */
class Woo_Cancel_Abandoned_Order_Integration_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::__construct
	 * @covers ::register_hooks
	 */
	public function test_define_integration_woo_cancel_abandoned_order_hooks(): void {

		$woo_cancel_abandoned_order = $this->make( Woo_Cancel_Abandoned_Order::class );

		$container_mock = $this->make(
			DI52_Container::class,
			array(
				'get' => Expected::once( $woo_cancel_abandoned_order ),
			)
		);

		\WP_Mock::expectFilterAdded(
			'woo_cao_gateways',
			array( $woo_cancel_abandoned_order, 'enable_cao_for_bitcoin' )
		);

		\WP_Mock::expectFilterAdded(
			'woo_cao_before_cancel_order',
			array( $woo_cancel_abandoned_order, 'abort_canceling_partially_paid_order' ),
			10,
			3
		);

		$sut = new Woo_Cancel_Abandoned_Order_Integration( $container_mock );
		$sut->register_hooks();
	}
}
