<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use Codeception\Test\Unit;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\Integrations\WooCommerce\Transaction_Formatter
 */
class Transaction_Formatter_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		\WP_Mock::setUp();

		\WP_Mock::passthruFunction( 'esc_url' );
	}

	protected function tearDown(): void {
		parent::tearDown();
		\WP_Mock::tearDown();
	}

	/**
	 * Test that get_url returns the correct blockchain.com explorer URL.
	 *
	 * @covers ::get_url
	 */
	public function test_get_url(): void {
		$txid = '3e4c8e1f2a7b6d9c5f8e3a1b4c7d9e2f5a8b1c4d7e0f3a6b9c2d5e8f1a4b7c0';

		$transaction = $this->makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid' => $txid,
			)
		);

		$result = Transaction_Formatter::get_url( $transaction );

		$this->assertEquals(
			'https://blockchain.com/explorer/transactions/btc/' . $txid,
			$result
		);
	}

	/**
	 * Test that get_ellipses returns the first 3 and last 3 characters with ellipses.
	 *
	 * @covers ::get_ellipses
	 */
	public function test_get_ellipses(): void {
		$txid = 'abcdefghijklmnopqrstuvwxyz123456';

		$transaction = $this->makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid' => $txid,
			)
		);

		$result = Transaction_Formatter::get_ellipses( $transaction );

		$this->assertEquals( 'abc...456', $result );
	}

	/**
	 * Test that get_ellipses works with a short transaction ID.
	 *
	 * @covers ::get_ellipses
	 */
	public function test_get_ellipses_with_short_txid(): void {
		$txid = '123456';

		$transaction = $this->makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid' => $txid,
			)
		);

		$result = Transaction_Formatter::get_ellipses( $transaction );

		$this->assertEquals( '123...456', $result );
	}

	/**
	 * Test get_order_note with a single transaction.
	 *
	 * @covers ::get_order_note
	 * @covers ::get_note_part
	 */
	public function test_get_order_note_single_transaction(): void {
		$txid = 'abc123def456ghi789jkl012mno345pqr678stu901vwx234yz567890abcdef12';

		$transaction = $this->makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid'         => $txid,
				'get_block_height' => 800000,
			)
		);

		$result = Transaction_Formatter::get_order_note( array( $transaction ) );

		// Should be singular "transaction" not plural.
		$this->assertStringContainsString( 'New transaction seen:', $result );
		$this->assertStringNotContainsString( 'New transactions seen:', $result );

		// Should contain the blockchain.com URL.
		$this->assertStringContainsString( 'https://blockchain.com/explorer/transactions/btc/' . $txid, $result );

		// Should contain the shortened txid.
		$this->assertStringContainsString( 'abc...f12', $result );

		// Should contain the block height.
		$this->assertStringContainsString( '@800000', $result );

		// Should contain HTML link elements.
		$this->assertStringContainsString( '<a href=', $result );
		$this->assertStringContainsString( 'target="_blank"', $result );

		// Should end with period and newlines.
		$this->assertStringEndsWith( ".\n\n", $result );
	}

	/**
	 * Test get_order_note with multiple transactions.
	 *
	 * @covers ::get_order_note
	 * @covers ::get_note_part
	 */
	public function test_get_order_note_multiple_transactions(): void {
		$transaction1 = $this->makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid'         => 'txid1111111111111111111111111111111111111111111111111111111111111',
				'get_block_height' => 700000,
			)
		);

		$transaction2 = $this->makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid'         => 'txid2222222222222222222222222222222222222222222222222222222222222',
				'get_block_height' => 700001,
			)
		);

		$result = Transaction_Formatter::get_order_note( array( $transaction1, $transaction2 ) );

		// Should be plural "transactions".
		$this->assertStringContainsString( 'New transactions seen:', $result );

		// Should contain both transaction URLs.
		$this->assertStringContainsString( 'txid1111111111111111111111111111111111111111111111111111111111111', $result );
		$this->assertStringContainsString( 'txid2222222222222222222222222222222222222222222222222222222222222', $result );

		// Should contain both block heights.
		$this->assertStringContainsString( '@700000', $result );
		$this->assertStringContainsString( '@700001', $result );

		// Should contain both shortened txids.
		$this->assertStringContainsString( 'txi...111', $result );
		$this->assertStringContainsString( 'txi...222', $result );

		// Should separate transactions with comma.
		$this->assertStringContainsString( ',', $result );
	}

	/**
	 * Test get_order_note with transaction in mempool (no block height).
	 *
	 * @covers ::get_order_note
	 * @covers ::get_note_part
	 */
	public function test_get_order_note_mempool_transaction(): void {
		$transaction = $this->makeEmpty(
			Transaction_Interface::class,
			array(
				'get_txid'         => 'mempool_txid1234567890abcdef1234567890abcdef1234567890abcdef1234',
				'get_block_height' => null, // Transaction in mempool has no block height.
			)
		);

		$result = Transaction_Formatter::get_order_note( array( $transaction ) );

		// Should show "mempool" instead of block height.
		$this->assertStringContainsString( '@mempool', $result );
		$this->assertStringNotContainsString( '@null', $result );
	}

	/**
	 * Test that get_order_note handles empty array.
	 *
	 * @covers ::get_order_note
	 */
	public function test_get_order_note_empty_array(): void {
		$result = Transaction_Formatter::get_order_note( array() );

		// Should still return a string with the header but no transaction data.
		$this->assertStringContainsString( 'New transactions seen:', $result );
		$this->assertStringEndsWith( ".\n\n", $result );
	}
}
