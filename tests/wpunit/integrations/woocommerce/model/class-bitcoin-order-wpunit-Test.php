<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Bitcoin_Wallet_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use lucatume\WPBrowser\TestCase\WPTestCase;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order
 */
class Bitcoin_Order_WPUnit_Test extends WPTestCase {

	/**
	 * @covers ::__construct
	 */
	public function test_get_id(): void {
		$order = new WC_Bitcoin_Order();
		$order->set_payment_method( 'bitcoin' );
		$order->add_meta_data( WC_Bitcoin_Order::BITCOIN_ADDRESS_META_KEY, 'xpub-address', true );
		$order->add_meta_data( WC_Bitcoin_Order::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY, 1234, true );
		$order->add_meta_data( WC_Bitcoin_Order::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY, 0.01, true );
		$order_id = $order->save();

		$result = $order->get_id();

		self::assertEquals( $order_id, $result );
	}

	/**
	 * @covers ::get_bitcoin_address
	 */
	public function test_get_address(): void {
		$bitcoin_address_mock = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => Expected::once( 'success' ),
				'get_tx_ids'      => Expected::once( array() ),
			),
		);

		$order = new WC_Bitcoin_Order();
		$order->set_payment_method( 'bitcoin' );
		$order->add_meta_data( WC_Bitcoin_Order::BITCOIN_ADDRESS_META_KEY, 'xpub-address', true );
		$order->add_meta_data( WC_Bitcoin_Order::EXCHANGE_RATE_AT_TIME_OF_PURCHASE_META_KEY, 1234, true );
		$order->add_meta_data( WC_Bitcoin_Order::ORDER_TOTAL_BITCOIN_AT_TIME_OF_PURCHASE_META_KEY, 0.01, true );
		$order_id = $order->save();

		$wallet_service  = $this->make(
			Bitcoin_Wallet_Service::class,
			array(
				'get_saved_address_by_bitcoin_payment_address' => Expected::once( $bitcoin_address_mock ),
			)
		);
		$payment_service = $this->make( Payment_Service::class );

		$order->hydrate(
			$wallet_service,
			$payment_service,
		);

		$result = $order->get_bitcoin_address();

		self::assertEquals( 'success', $result->get_raw_address() );
	}

	/**
	 * @covers ::get_raw_payment_address
	 */
	public function test_get_raw_payment_address(): void {

		$sut = new WC_Bitcoin_Order();
		$sut->add_meta_data( WC_Bitcoin_Order::BITCOIN_ADDRESS_META_KEY, 'xpub-address', true );
		$sut->save();

		$result = $sut->get_raw_payment_address();

		self::assertEquals( 'xpub-address', $result );
	}

	/**
	 * @covers ::set_raw_address
	 */
	public function test_set_address(): void {

		$payment_address = new Bitcoin_Address(
			post_id: 123,
			wallet_parent_post_id: 456,
			raw_address: 'xpub1',
			derivation_path_sequence_number: 7,
			created_time: new DateTimeImmutable(),
			modified_time: new DateTimeImmutable(),
		);

		$sut = new WC_Bitcoin_Order();
		$sut->save();

		$sut->set_raw_address( $payment_address );
		$result = $sut->get_raw_payment_address();

		self::assertEquals( 'xpub1', $result );
	}

	/**
	 * @covers ::set_btc_total_price
	 * @covers ::get_btc_total_price
	 */
	public function test_btc_total_price(): void {

		$sut = new WC_Bitcoin_Order();
		$sut->save();

		$sut->set_json_mapper( new JsonMapper_Helper()->build() );

		$sut->set_btc_total_price( Money::of( 2, 'BTC' ) );

		$result = $sut->get_btc_total_price();

		$this->assertTrue( Money::of( 2, 'BTC' )->isEqualTo( $result ) );
	}

	/**
	 * @covers ::set_exchange_rate
	 * @covers ::get_exchange_rate
	 */
	public function test_exchange_rate(): void {

		$sut = new WC_Bitcoin_Order();
		$sut->save();

		$sut->set_json_mapper( new JsonMapper_Helper()->build() );

		$sut->set_exchange_rate( Money::of( '0.0987', 'BTC' ) );

		$result = $sut->get_exchange_rate();

		$this->assertTrue( Money::of( '0.0987', 'BTC' )->isEqualTo( $result ) );
	}

	/**
	 * @covers ::set_confirmed_amount_received
	 * @covers ::get_confirmed_amount_received
	 */
	public function test_confirmed_amount_received(): void {

		$sut = new WC_Bitcoin_Order();
		$sut->save();

		$sut->set_json_mapper( new JsonMapper_Helper()->build() );

		$sut->set_confirmed_amount_received( Money::of( '0.0024', 'BTC' ) );

		$result = $sut->get_confirmed_amount_received();

		$this->assertTrue( Money::of( '0.0024', 'BTC' )->isEqualTo( $result ) );
	}
}
