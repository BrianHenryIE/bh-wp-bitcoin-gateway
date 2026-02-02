<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use lucatume\WPBrowser\TestCase\WPTestCase;
use ReflectionProperty;
use WP_Post;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address
 */
class Bitcoin_Address_WPUnit_Test extends \lucatume\WPBrowser\TestCase\WPTestCase {

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

		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build(), new ColorLogger() );
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$sut = $bitcoin_address_repository->get_by_post_id( $post_id );

		$result = $sut->get_amount_received();

		$this->assertEquals( '1.23456789', $result?->getAmount() );
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

		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build(), new ColorLogger() );
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

		$bitcoin_address_factory    = new Bitcoin_Address_Factory( new JsonMapper_Helper()->build(), new ColorLogger() );
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );

		$sut = $bitcoin_address_repository->get_by_post_id( $post_id );

		$result = $sut->get_amount_received();

		$this->assertNull( $result );
	}
}
