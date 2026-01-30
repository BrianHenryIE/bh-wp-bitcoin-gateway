<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use ReflectionProperty;
use WP_Post;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address
 */
class Bitcoin_Address_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * When using `update_post_meta()` the last modified time of the post does not change. This
	 * is a test to see will it update if we use `update_post( array( 'meta_input' => array() )` instead.
	 */
	public function test_last_modified_time_is_updated(): void {

		$this->markTestIncomplete( 'Manipulation fo the object has moved to the repository.' );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$wallet = $this->makeEmpty( Bitcoin_Wallet::class );

		$bitcoin_address = $bitcoin_address_repository->save_new_address(
			wallet:             $wallet,
			derivation_path_sequence_index: 2,
			address: 'address',
		);

		/** @var WP_Post $bitcoin_address_post */
		$bitcoin_address_post = get_post( $bitcoin_address->get_post_id() );

		$last_modified_time_before = $bitcoin_address_post->post_modified_gmt;

		$bitcoin_address_object = $bitcoin_address_repository->get_by_post_id( $bitcoin_address->get_post_id() );

		sleep( 1 );

		$bitcoin_address_object->set_order_id( 123 );

		/** @var WP_Post $bitcoin_address_post */
		$bitcoin_address_post = get_post( $bitcoin_address->get_post_id() );

		$last_modified_time_after = $bitcoin_address_post->post_modified_gmt;

		$this->assertNotEquals( $last_modified_time_before, $last_modified_time_after );
	}

	/**
	 * @covers ::get_order_id
	 */
	public function test_get_order_id_null_before_set(): void {

		$this->markTestIncomplete( 'should it be possible for a Bitcoin_Address object to exist without a post_id?' );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$wallet = $this->makeEmpty( Bitcoin_Wallet::class );

		$bitcoin_address = $bitcoin_address_repository->save_new_address(
			wallet:             $wallet,
			derivation_path_sequence_index: 2,
			address: 'address',
		);

		$sut = $bitcoin_address_repository->get_by_post_id( $bitcoin_address->get_post_id() );

		$result = $sut->get_order_id();

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_order_id
	 */
	public function test_get_order_id_after_set(): void {

		$this->markTestIncomplete( 'should it be possible for a Bitcoin_Address object to exist without a post_id?' );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$wallet = $this->makeEmpty( Bitcoin_Wallet::class );

		$bitcoin_address_post_id = $bitcoin_address_repository->save_new_address(
			new Bitcoin_Address_Query(
				wallet_wp_post_parent_id: $wallet->get_post_id(),
				xpub: 'address',
				derivation_path_sequence_index: 2
			)
		);

		$sut = $bitcoin_address_repository->get_by_post_id( $bitcoin_address_post_id );

		$sut->set_order_id( 123 );

		$sut = $bitcoin_address_repository->get_by_post_id( $bitcoin_address_post_id );

		$result = $sut->get_order_id();

		$this->assertEquals( 123, $result );
	}

	/**
	 * @covers ::get_amount_received
	 */
	public function test_get_received_used(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'bh-bitcoin-address',
				'post_status' => 'used',
				'meta_input'  => array(
					Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY => 123,
					Bitcoin_Address_WP_Post_Interface::CONFIRMED_AMOUNT_RECEIVED_META_KEY => json_encode(
						array(
							'amount'   => '1.23456789',
							'currency' => 'BTC',
						)
					),
				),
			)
		);

		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build() );
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$sut = $bitcoin_address_repository->get_by_post_id( $post_id );

		$result = $sut->get_amount_received();

		$this->assertEquals( '1.23456789', $result?->getAmount() );
	}

	/**
	 * @covers ::get_amount_received
	 */
	public function test_get_target_amount(): void {

		$this->markTestIncomplete( 'This is moved to the repository' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'bh-bitcoin-address',
				'post_status' => 'used',
				'meta_input'  => array(
					Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY => 123,
				),
			)
		);

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$sut = $bitcoin_address_repository->get_by_post_id( $post_id );

		$sut->assign( 123, Money::of( '0.000123', 'BTC' ) );

		$sut    = $bitcoin_address_repository->get_by_post_id( $post_id );
		$result = $sut->get_target_amount();

		$this->assertEquals( '0.000123', $result?->getAmount()->toFloat() );
	}

	/**
	 * @covers ::get_amount_received
	 */
	public function test_get_received_unused(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'bh-bitcoin-address',
				'post_status' => 'unused',
				'meta_input'  => array(
					Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY => 123,
				),
			)
		);

		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build() );
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$sut = $bitcoin_address_repository->get_by_post_id( $post_id );

		$result = $sut->get_amount_received();

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_amount_received
	 */
	public function test_get_received_unknown(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'bh-bitcoin-address',
				'post_status' => 'unknown',
				'meta_input'  => array(
					Bitcoin_Address_WP_Post_Interface::DERIVATION_PATH_SEQUENCE_NUMBER_META_KEY => 123,
				),
			)
		);

		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build() );
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$sut = $bitcoin_address_repository->get_by_post_id( $post_id );

		$result = $sut->get_amount_received();

		$this->assertNull( $result );
	}

	/**
	 * Test the immediately invoked function which throws an exception does not run until the null coalesce operator
	 * evaluates the left hand side.
	 */
	public function test_refresh_address(): void {
		$this->markTestIncomplete( 'should a Bitcoin_Address object have setters?' );

		$post_property = new ReflectionProperty( Bitcoin_Address::class, 'post' );

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$wallet = $this->makeEmpty( Bitcoin_Wallet::class );

		$bitcoin_address_post_id = $bitcoin_address_repository->save_new_address(
			new Bitcoin_Address_Query(
				wallet_wp_post_parent_id: $wallet->get_post_id(),
				xpub: 'address',
				derivation_path_sequence_index: 2
			)
		);

		$bitcoin_address_object = $bitcoin_address_repository->get_by_post_id( $bitcoin_address_post_id );

		( fn() => $this->refresh_wp_post() )->call( $bitcoin_address_object );

		/** @var WP_Post $post */
		$post     = $post_property->getValue( $bitcoin_address_object );
		$post->ID = 999;

		$this->expectException( \RuntimeException::class );

		( fn() => $this->refresh_wp_post() )->call( $bitcoin_address_object );
	}
}
