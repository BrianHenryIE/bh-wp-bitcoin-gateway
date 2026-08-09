<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
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
}
