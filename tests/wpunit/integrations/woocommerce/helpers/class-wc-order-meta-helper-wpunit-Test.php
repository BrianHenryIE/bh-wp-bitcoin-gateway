<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Helpers;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;
use WC_Order;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Helpers\WC_Order_Meta_Helper
 */
class WC_Order_Meta_Helper_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	protected function get_sut(): WC_Order_Meta_Helper {
		return new WC_Order_Meta_Helper(
			new JsonMapper_Helper()->build()
		);
	}

	/**
	 * @covers ::get_raw_payment_address
	 */
	public function test_get_raw_payment_address(): void {

		$wc_order = new WC_Order();
		$wc_order->add_meta_data( WC_Order_Meta_Helper::BITCOIN_ADDRESS_META_KEY, 'xpub-address', true );
		$wc_order->save();

		$sut = $this->get_sut();

		$result = $sut->get_raw_payment_address( $wc_order );

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

		$order = new WC_Order();
		$order->save();

		$sut = $this->get_sut();

		$sut->set_raw_address( $order, $payment_address );
		$result = $sut->get_raw_payment_address( $order );

		self::assertEquals( 'xpub1', $result );
	}

	/**
	 * @covers ::set_btc_total_price
	 * @covers ::get_btc_total_price
	 */
	public function test_btc_total_price(): void {

		$wc_order = new WC_Order();
		$wc_order->save();

		$sut = $this->get_sut();

		$sut->set_btc_total_price( $wc_order, Money::of( 2, 'BTC' ) );

		$result = $sut->get_btc_total_price( $wc_order );

		$this->assertTrue( Money::of( 2, 'BTC' )->isEqualTo( $result ) );
	}

	/**
	 * @covers ::set_exchange_rate
	 * @covers ::get_exchange_rate
	 */
	public function test_exchange_rate(): void {

		$wc_order = new WC_Order();
		$wc_order->save();

		$sut = $this->get_sut();

		$sut->set_exchange_rate( $wc_order, Money::of( 0.0987, 'BTC' ) );

		$result = $sut->get_exchange_rate( $wc_order );

		$this->assertTrue( Money::of( 0.0987, 'BTC' )->isEqualTo( $result ) );
	}

	/**
	 * @covers ::set_confirmed_amount_received
	 * @covers ::get_confirmed_amount_received
	 */
	public function test_confirmed_amount_received(): void {

		$wc_order = new WC_Order();
		$wc_order->save();

		$sut = $this->get_sut();

		$sut->set_confirmed_amount_received( $wc_order, Money::of( 0.0024, 'BTC' ) );

		$result = $sut->get_confirmed_amount_received( $wc_order );

		$this->assertTrue( Money::of( 0.0024, 'BTC' )->isEqualTo( $result ) );
	}
}
