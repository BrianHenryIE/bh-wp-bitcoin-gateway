<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Transaction_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Wallet_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Wallet_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository
 */
class Bitcoin_Transaction_Repository_WPUnit_Test extends WPTestCase {

	/**
	 * Save a transaction to the wp_post table, then try to load it again!
	 *
	 * @covers ::save_post
	 */
	public function test_save_post(): void {

		$bitcoin_wallet_factory    = new Bitcoin_Wallet_Factory();
		$bitcoin_wallet_repository = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );

		$wallet = $bitcoin_wallet_repository->save_new( 'xpub123' );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build(), new ColorLogger() );
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$bitcoin_address = $bitcoin_address_repository->save_new_address( $wallet, 1, 'payment_address_345' );

		$json_mapper                 = new JsonMapper_Helper()->build();
		$bitcoin_transaction_factory = new Bitcoin_Transaction_Factory( $json_mapper );
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
					value: Money::of( 2, 'BTC' )->dividedBy( Exchange_Rate_Service::SATOSHI_RATE ),
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

	/**
	 * @return array{sut:Bitcoin_Transaction_Repository, address:\BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address, address_repository:Bitcoin_Address_Repository, wallet:\BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet}
	 */
	protected function get_sut_with_address(): array {
		$bitcoin_wallet_repository = new Bitcoin_Wallet_Repository( new Bitcoin_Wallet_Factory() );
		$wallet                    = $bitcoin_wallet_repository->save_new( 'xpub_dup_' . wp_rand() );

		$bitcoin_address_repository = new Bitcoin_Address_Repository( new Bitcoin_Address_Factory( new JsonMapper_Helper()->build(), new ColorLogger() ) );
		$bitcoin_address            = $bitcoin_address_repository->save_new_address( $wallet, 1, 'bc1q_dup_' . wp_rand() );

		$sut = new Bitcoin_Transaction_Repository( new Bitcoin_Transaction_Factory( new JsonMapper_Helper()->build() ) );

		return array(
			'sut'                => $sut,
			'address'            => $bitcoin_address,
			'address_repository' => $bitcoin_address_repository,
			'wallet'             => $wallet,
		);
	}

	protected function make_transaction( string $tx_id, ?int $block_height, string $to_address ): Transaction {
		return new Transaction(
			tx_id: $tx_id,
			block_time: is_null( $block_height ) ? null : new DateTimeImmutable( '@1790265450' ),
			version: 2,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( '0.00005934', 'BTC' ),
					scriptpubkey_address: $to_address,
				),
			),
			block_height: $block_height,
		);
	}

	protected function count_transaction_posts(): int {
		return count(
			get_posts(
				array(
					'post_type'   => \BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction_WP_Post_Interface::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
				)
			)
		);
	}

	/**
	 * A transaction is fetched every time its address is checked; it must not create a post each time.
	 *
	 * @covers ::save_new
	 * @covers ::save_post
	 * @covers ::get_post_by_transaction_id
	 */
	public function test_save_new_twice_returns_the_same_post(): void {
		[ 'sut' => $sut, 'address' => $address ] = $this->get_sut_with_address();

		$transaction = $this->make_transaction( 'd6e7ea848fa17ee7aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 968417, $address->get_raw_address() );

		$first  = $sut->save_new( $transaction, $address );
		$second = $sut->save_new( $transaction, $address );

		$this->assertSame( $first->get_post_id(), $second->get_post_id() );
		$this->assertSame( 1, $this->count_transaction_posts() );
	}

	/**
	 * Without an explicit status `wp_insert_post()` saves a draft, and without the txid as slug the post can never
	 * be found again.
	 *
	 * @covers ::save_post
	 */
	public function test_save_new_publishes_with_txid_slug(): void {
		[ 'sut' => $sut, 'address' => $address ] = $this->get_sut_with_address();

		$tx_id = 'd6e7ea848fa17ee7bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
		$saved = $sut->save_new( $this->make_transaction( $tx_id, 968417, $address->get_raw_address() ), $address );

		$post = get_post( $saved->get_post_id() );

		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $tx_id, $post->post_name );
		$this->assertSame( $tx_id, $post->post_title );
	}

	/**
	 * A transaction first seen in the mempool is fetched again once mined; the saved copy (which the payment
	 * service uses for confirmation maths) must pick up the block height.
	 *
	 * @covers ::save_post
	 */
	public function test_save_new_updates_mempool_transaction_once_confirmed(): void {
		[ 'sut' => $sut, 'address' => $address ] = $this->get_sut_with_address();

		$tx_id = 'd6e7ea848fa17ee7cccccccccccccccccccccccccccccccccccccccccccccccc';

		$in_mempool = $sut->save_new( $this->make_transaction( $tx_id, null, $address->get_raw_address() ), $address );
		$this->assertNull( $in_mempool->get_block_height() );

		$mined = $sut->save_new( $this->make_transaction( $tx_id, 968417, $address->get_raw_address() ), $address );

		$this->assertSame( $in_mempool->get_post_id(), $mined->get_post_id() );
		$this->assertSame( 968417, $mined->get_block_height() );
		$this->assertSame( 968417, $sut->get_by_post_id( $mined->get_post_id() )->get_block_height() );
		$this->assertSame( 1, $this->count_transaction_posts() );
	}

	/**
	 * One transaction can pay two of our addresses; saving it for the second must keep the first.
	 *
	 * @covers ::save_post
	 * @covers ::get_bitcoin_addresses_meta
	 */
	public function test_save_new_for_second_address_merges_address_meta(): void {
		[ 'sut' => $sut, 'address' => $address_a, 'address_repository' => $address_repository, 'wallet' => $wallet ] = $this->get_sut_with_address();
		$address_b = $address_repository->save_new_address( $wallet, 2, 'bc1q_dup_b_' . wp_rand() );

		$tx_id       = 'd6e7ea848fa17ee7dddddddddddddddddddddddddddddddddddddddddddddddd';
		$transaction = $this->make_transaction( $tx_id, 968417, $address_a->get_raw_address() );

		$sut->save_new( $transaction, $address_a );
		$saved = $sut->save_new( $transaction, $address_b );

		$this->assertSame( 1, $this->count_transaction_posts() );

		$meta      = get_post_meta(
			$saved->get_post_id(),
			\BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction_WP_Post_Interface::BITCOIN_ADDRESSES_POST_IDS_META_KEY,
			true
		);
		$addresses = is_string( $meta ) ? json_decode( $meta, true ) : $meta;

		$this->assertEqualsCanonicalizing(
			array(
				$address_a->get_post_id() => $address_a->get_raw_address(),
				$address_b->get_post_id() => $address_b->get_raw_address(),
			),
			$addresses
		);
	}
}
