<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use Codeception\Stub\Expected;
use stdClass;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\Woo_Cancel_Abandoned_Order\Woo_Cancel_Abandoned_Order
 */
class Woo_Cancel_Abandoned_Order_Unit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::enable_cao_for_bitcoin
	 * @covers ::__construct
	 */
	public function test_enable_cao_for_bitcoin(): void {

		$bitcoin_gateways    = array();
		$bitcoin_gateway     = new stdClass();
		$bitcoin_gateway->id = 'bitcoin_gateway_1';
		$bitcoin_gateways[]  = $bitcoin_gateway;

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_gateways' => Expected::once( $bitcoin_gateways ),
			)
		);

		$sut = new Woo_Cancel_Abandoned_Order( $api );

		$gateway_ids = array();

		$result = $sut->enable_cao_for_bitcoin( $gateway_ids );

		$this->assertContains( 'bitcoin_gateway_1', $result );
	}

	/**
	 * @covers ::abort_canceling_partially_paid_order
	 */
	public function test_abort_canceling_partially_paid_order(): void {

		$bitcoin_address_mock = self::make(
			Bitcoin_Address::class,
			array(
				'get_blockchain_transactions' => Expected::once( array( 'not', 'empty' ) ),
			)
		);

		$bitcoin_order_mock = self::makeEmpty(
			WC_Bitcoin_Order::class,
			array(
				'get_address' => Expected::once( $bitcoin_address_mock ),
			)
		);

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once( true ),
				'get_order_details'            => Expected::once( $bitcoin_order_mock ),
			)
		);

		$sut = new Woo_Cancel_Abandoned_Order( $api );

		$should_cancel = true;

		$order    = new WC_Order();
		$order_id = $order->save();

		$result = $sut->abort_canceling_partially_paid_order( $should_cancel, $order_id, $order );

		$this->assertFalse( $result );
	}


	/**
	 * @covers ::abort_canceling_partially_paid_order
	 */
	public function test_abort_canceling_partially_paid_order_not_bicoin_gateway(): void {

		$order_details = array(
			'transactions' => array( 'tx1', 'tx2' ),
		);

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once( false ),
				'get_order_details'            => Expected::never(),
			)
		);

		$sut = new Woo_Cancel_Abandoned_Order( $api );

		$should_cancel = true;

		$order    = new WC_Order();
		$order_id = $order->save();

		$result = $sut->abort_canceling_partially_paid_order( $should_cancel, $order_id, $order );

		$this->assertTrue( $result );
	}


	/**
	 * @covers ::abort_canceling_partially_paid_order
	 */
	public function test_abort_canceling_partially_paid_order_no_transactions(): void {

		$address_mock       = self::makeEmpty(
			Bitcoin_Address::class,
			array(
				'get_blockchain_transactions' => Expected::once( array() ),
			)
		);
		$bitcoin_order_mock = self::makeEmpty(
			WC_Bitcoin_Order::class,
			array(
				'get_address' => Expected::once( $address_mock ),
			)
		);

		$api = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'is_order_has_bitcoin_gateway' => Expected::once( true ),
				'get_order_details'            => Expected::once( $bitcoin_order_mock ),
			)
		);

		$sut = new Woo_Cancel_Abandoned_Order( $api );

		$should_cancel = true;

		$order    = new WC_Order();
		$order_id = $order->save();

		$result = $sut->abort_canceling_partially_paid_order( $should_cancel, $order_id, $order );

		$this->assertTrue( $result );
	}
}
