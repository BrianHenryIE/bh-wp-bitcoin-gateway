<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Codeception\Stub\Expected;
use WC_Order;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Details_Formatter
 */
class Details_Formatter_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	protected function get_sut(
		?WC_Bitcoin_Order $bitcoin_order = null,
	): Details_Formatter {
		return new Details_Formatter(
			bitcoin_order: $bitcoin_order ?? $this->make( WC_Bitcoin_Order::class ),
		);
	}

	/**
	 * @covers ::get_wc_order_status_formatted
	 * @covers ::__construct
	 */
	public function test_get_wc_order_status_formatted(): void {

		$bitcoin_order = $this->make(
			WC_Bitcoin_Order::class,
			array(
				'get_status' => Expected::once( 'on-hold' ),
			)
		);

		$sut = $this->get_sut( $bitcoin_order );

		$result = $sut->get_wc_order_status_formatted();

		self::assertEquals( 'On hold', $result );
	}

	/**
	 * @covers ::get_xpub_js_span
	 */
	public function test_get_xpub_js_span(): void {

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address' => 'xpub1a2s3d4f5gabcdef',
			)
		);

		$bitcoin_order = $this->make(
			WC_Bitcoin_Order::class,
			array(
				'get_bitcoin_address' => $address,
			)
		);

		$sut = $this->get_sut( $bitcoin_order );

		$result = $sut->get_xpub_js_span();

		self::assertStringContainsString( 'xpub1a2 ... def', $result );
		self::assertStringContainsString( 'onclick', $result );
	}

	/**
	 * @param string $confirmed The confirmed amount received at the address.
	 * @param string $unconfirmed The amount seen in the mempool / with too few confirmations.
	 * @param bool   $is_paid Whether WooCommerce considers the order paid.
	 */
	protected function get_sut_with_amounts( string $confirmed, string $unconfirmed, bool $is_paid = false ): Details_Formatter {
		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_amount_received'             => Money::of( $confirmed, 'BTC' ),
				'get_unconfirmed_amount_received' => Money::of( $unconfirmed, 'BTC' ),
				'get_target_amount'               => Money::of( '0.01', 'BTC' ),
			)
		);

		$bitcoin_order = $this->make(
			WC_Bitcoin_Order::class,
			array(
				'get_bitcoin_address' => $address,
				'is_paid'             => $is_paid,
				'get_btc_total_price' => Money::of( '0.01', 'BTC' ),
			)
		);

		return $this->get_sut( $bitcoin_order );
	}

	/**
	 * @covers ::get_payment_status_key
	 * @covers ::get_friendly_status
	 */
	public function test_status_awaiting_payment_when_nothing_seen(): void {
		$sut = $this->get_sut_with_amounts( '0', '0' );

		$this->assertSame( Details_Formatter::STATUS_AWAITING_PAYMENT, $sut->get_payment_status_key() );
		$this->assertSame( 'Awaiting Payment', $sut->get_friendly_status() );
	}

	/**
	 * The full amount in the mempool: the customer has paid, we are waiting for confirmations.
	 *
	 * @covers ::get_payment_status_key
	 * @covers ::get_friendly_status
	 * @covers ::get_btc_amount_unconfirmed_formatted
	 */
	public function test_status_awaiting_confirmation_when_full_amount_in_mempool(): void {
		$sut = $this->get_sut_with_amounts( '0', '0.01' );

		$this->assertSame( Details_Formatter::STATUS_AWAITING_CONFIRMATION, $sut->get_payment_status_key() );
		$this->assertSame( 'Payment seen, awaiting confirmation', $sut->get_friendly_status() );
		$this->assertSame( '฿ 0.01000000', $sut->get_btc_amount_unconfirmed_formatted() );
	}

	/**
	 * @covers ::get_payment_status_key
	 */
	public function test_status_awaiting_confirmation_when_split_between_confirmed_and_unconfirmed(): void {
		$sut = $this->get_sut_with_amounts( '0.004', '0.006' );

		$this->assertSame( Details_Formatter::STATUS_AWAITING_CONFIRMATION, $sut->get_payment_status_key() );
	}

	/**
	 * @covers ::get_payment_status_key
	 * @covers ::get_friendly_status
	 */
	public function test_status_partly_paid_when_less_than_total_seen(): void {
		$sut = $this->get_sut_with_amounts( '0', '0.005' );

		$this->assertSame( Details_Formatter::STATUS_PARTLY_PAID, $sut->get_payment_status_key() );
		$this->assertSame( 'Partly Paid', $sut->get_friendly_status() );
	}

	/**
	 * @covers ::get_payment_status_key
	 * @covers ::to_array
	 */
	public function test_status_paid_and_keys_in_array(): void {
		$sut = $this->get_sut_with_amounts( '0.01', '0', true );

		$this->assertSame( Details_Formatter::STATUS_PAID, $sut->get_payment_status_key() );
	}
}
