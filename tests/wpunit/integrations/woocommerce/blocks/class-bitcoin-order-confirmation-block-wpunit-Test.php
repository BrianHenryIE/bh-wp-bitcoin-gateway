<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\API_WooCommerce_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Settings_Interface;
use Codeception\Stub\Expected;
use lucatume\WPBrowser\TestCase\WPTestCase;
use WP_Block;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Blocks\Bitcoin_Order_Confirmation_Block
 */
class Bitcoin_Order_Confirmation_Block_WPUnit_Test extends WPTestCase {

	/**
	 * The block renders on the customer's thank-you page; a failure loading the order must log and render
	 * nothing rather than fatal.
	 *
	 * @covers ::__construct
	 * @covers ::render_block
	 * @covers ::get_order_details_formatted_array
	 * @covers ::get_order
	 */
	public function test_render_block_logs_and_returns_empty_when_order_load_throws(): void {

		$api    = $this->makeEmpty(
			API_WooCommerce_Interface::class,
			array(
				'get_bitcoin_order' => Expected::atLeastOnce(
					function () {
						throw new \TypeError( 'Call to a member function getTimestamp() on null' );
					}
				),
			)
		);
		$logger = new ColorLogger();

		$sut = new Bitcoin_Order_Confirmation_Block( $this->makeEmpty( Settings_Interface::class ), $api, $logger );

		$GLOBALS['order-received'] = 123;

		$block = new WP_Block( array( 'blockName' => 'bh-wp-bitcoin-gateway/bitcoin-order' ) );

		$result = $sut->render_block( array( 'orderId' => 123 ), '<p>inner</p>', $block );

		$this->assertStringContainsString( 'bh-wp-bitcoin-gateway-bitcoin-order-container', $result );
		$this->assertTrue( $logger->hasWarningThatContains( 'getTimestamp() on null' ) );

		unset( $GLOBALS['order-received'] );
	}
}
