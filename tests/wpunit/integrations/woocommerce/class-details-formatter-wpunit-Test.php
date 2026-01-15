<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Model\WC_Bitcoin_Order;
use WC_Order;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Details_Formatter
 */
class Details_Formatter_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @covers ::get_wc_order_status_formatted
	 * @covers ::__construct
	 */
	public function test_get_wc_order_status_formatted(): void {

		$wc_order      = $this->make(
			WC_Order::class,
			array(
				'get_status' => 'on-hold',
			)
		);
		$bitcoin_order = $this->make(
			WC_Bitcoin_Order::class,
			array(
				'wc_order' => $wc_order,
			)
		);

		$sut = new Details_Formatter( $bitcoin_order );

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
				'get_address' => $address,
			)
		);

		$sut = new Details_Formatter( $bitcoin_order );

		$result = $sut->get_xpub_js_span();

		self::assertStringContainsString( 'xpub1a2 ... def', $result );
		self::assertStringContainsString( 'onclick', $result );
	}
}
