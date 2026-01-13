<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository
 */
class Bitcoin_Transaction_Repository_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Save a transaction to the wp_post table, then try to load it again!
	 *
	 * @covers ::save_post
	 */
	public function test_save_post(): void {

		$bitcoin_wallet_factory    = new Bitcoin_Wallet_Factory();
		$bitcoin_wallet_repository = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );

		$wallet = $bitcoin_wallet_repository->save_new( 'xpub123' );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$bitcoin_address = $bitcoin_address_repository->save_new( $wallet, 1, 'payment_address_345' );

		$bitcoin_transaction_factory = new Bitcoin_Transaction_Factory();
		$sut                         = new Bitcoin_Transaction_Repository( $bitcoin_transaction_factory );

		$block_time = new DateTimeImmutable()->setDate( 2020, 02, 27 )->setTime( 11, 46, 35 );

		$transaction_to_save = new Transaction(
			tx_id: '6b1942ad9572d9675017a3a082e4e3f2dd857ce3e9c34dc8eff0c5b8babf0408',
			block_time: $block_time,
			version: 2,
			v_in: array(
				new Transaction_VIn(
					sequence: 123,
					scriptsig: 'abc',
					address: 'payment_address_345',
					prevout_scriptpubkey: 'def',
					value: Money::of( 123, 'BTC' ),
					prev_out_n: 1,
				),
			),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 2, 'BTC' )->dividedBy( 100_000_000 ),
					scriptpubkey_address: 'addr',
				),
			),
			block_height: 619213,
		);

		$result = $sut->save_new( $transaction_to_save, $bitcoin_address );

		$this->assertEquals( '6b1942ad9572d9675017a3a082e4e3f2dd857ce3e9c34dc8eff0c5b8babf0408', $result->get_txid() );
		$this->assertEquals( 2, $result->get_version() );
		$this->assertEquals( $block_time->format( 'U' ), $result->get_block_time()->format( 'U' ) );
		$this->assertEquals( 619213, $result->get_block_height() );
	}
}
