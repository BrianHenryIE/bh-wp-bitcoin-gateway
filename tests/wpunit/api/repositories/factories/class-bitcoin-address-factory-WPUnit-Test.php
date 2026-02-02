<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Helper;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address_WP_Post_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Wallet;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Address_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperInterface;
use Codeception\Stub\Expected;
use lucatume\WPBrowser\TestCase\WPTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Factories\Bitcoin_Address_Factory
 */
class Bitcoin_Address_Factory_WPUnit_Test extends WPTestCase {

	protected function get_sut(
		?JsonMapperInterface $json_mapper = null,
		?LoggerInterface $logger = null
	): Bitcoin_Address_Factory {
		return new Bitcoin_Address_Factory(
			json_mapper: $json_mapper ?? new JsonMapper_Helper()->build(),
			logger: $logger ?? new ColorLogger()
		);
	}

	/**
	 * @covers ::get_order_id_from_post
	 */
	public function test_get_order_id_null_before_set(): void {

		$sut = $this->get_sut();

		$bitcoin_address_repository = new Bitcoin_Address_Repository( $sut );
		$wallet                     = $this->makeEmpty( Bitcoin_Wallet::class );

		$bitcoin_address = $bitcoin_address_repository->save_new_address(
			wallet: $wallet,
			derivation_path_sequence_index: 2,
			address: 'address',
		);

		$sut = $sut->get_by_wp_post_id( $bitcoin_address->get_post_id() );

		$result = $sut->get_order_id();

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_order_id_from_post
	 */
	public function test_get_order_id_after_set(): void {

		$sut = $this->get_sut();

		$bitcoin_address_repository = new Bitcoin_Address_Repository( $sut );
		$wallet                     = $this->makeEmpty( Bitcoin_Wallet::class );

		$new_payment_address = $bitcoin_address_repository->save_new_address(
			wallet: $wallet,
			derivation_path_sequence_index: 2,
			address: 'address'
		);

		$bitcoin_address_repository->assign_to_order(
			address: $new_payment_address,
			integration_id: __METHOD__,
			order_id: 123,
			btc_total: Money::of( 0.00002, 'BTC' )
		);

		$new_payment_address_after = $bitcoin_address_repository->refresh( $new_payment_address );

		$result = $new_payment_address_after->get_order_id();

		$this->assertEquals( 123, $result );
	}

	/**
	 * @covers ::get_target_amount_from_post
	 * @covers ::get_json_mapped_money_from_post
	 */
	public function test_get_target_amount_from_post_with_valid_money(): void {
		$money      = Money::of( '100.50', 'USD' );
		$money_json = wp_json_encode( $money );

		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, $money_json );

		$post = get_post( $post_id );
		$sut  = $this->get_sut();

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_target_amount_from_post' );

		$result = $method->invoke( $sut, $post );

		$this->assertInstanceOf( Money::class, $result );
		$this->assertEquals( '100.50', $result->getAmount()->__toString() );
		$this->assertEquals( 'USD', $result->getCurrency()->getCurrencyCode() );
	}

	/**
	 * @covers ::get_target_amount_from_post
	 * @covers ::get_json_mapped_money_from_post
	 * @covers ::log_meta_value_warning
	 */
	public function test_get_target_amount_from_post_with_invalid_json(): void {
		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, 'invalid json' );

		$post = get_post( $post_id );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_target_amount_from_post' );

		$result = $method->invoke( $sut, $post );

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_target_amount_from_post
	 * @covers ::get_json_mapped_money_from_post
	 * @covers ::log_meta_value_warning
	 */
	public function test_get_target_amount_from_post_with_non_string_value(): void {
		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta(
			$post_id,
			Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY,
			array(
				'amount'   => '100',
				'currency' => 'USD',
			)
		);

		$post = get_post( $post_id );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_target_amount_from_post' );

		$result = $method->invoke( $sut, $post );

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_target_amount_from_post
	 * @covers ::get_json_mapped_money_from_post
	 */
	public function test_get_target_amount_from_post_with_no_meta(): void {
		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		$post = get_post( $post_id );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::never(),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_target_amount_from_post' );

		$result = $method->invoke( $sut, $post );

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_received_from_post
	 * @covers ::get_json_mapped_money_from_post
	 */
	public function test_get_received_from_post_with_valid_money(): void {
		$money      = Money::of( '0.00012345', 'BTC' );
		$money_json = wp_json_encode( $money );

		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::CONFIRMED_AMOUNT_RECEIVED_META_KEY, $money_json );

		$post = get_post( $post_id );
		$sut  = $this->get_sut();

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_received_from_post' );

		$result = $method->invoke( $sut, $post );

		$this->assertInstanceOf( Money::class, $result );
		$this->assertEquals( 'BTC', $result->getCurrency()->getCurrencyCode() );
	}

	/**
	 * @covers ::get_received_from_post
	 * @covers ::get_json_mapped_money_from_post
	 * @covers ::log_meta_value_warning
	 */
	public function test_get_received_from_post_with_invalid_json(): void {
		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::CONFIRMED_AMOUNT_RECEIVED_META_KEY, '{invalid json}' );

		$post = get_post( $post_id );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_received_from_post' );

		$result = $method->invoke( $sut, $post );

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_json_mapped_money_from_post
	 */
	public function test_get_json_mapped_money_from_post_with_valid_btc_amount(): void {
		$money      = Money::of( '0.0000543', 'BTC' );
		$money_json = wp_json_encode( $money );

		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, $money_json );

		$sut = $this->get_sut();

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_json_mapped_money_from_post' );

		$result = $method->invoke(
			$sut,
			$post_id,
			Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY
		);

		$this->assertInstanceOf( Money::class, $result );
		$this->assertTrue( $result->getAmount()->isEqualTo( '0.0000543' ) );
		$this->assertEquals( 'BTC', $result->getCurrency()->getCurrencyCode() );
	}

	/**
	 * @covers ::get_json_mapped_money_from_post
	 * @covers ::log_meta_value_warning
	 */
	public function test_get_json_mapped_money_from_post_with_empty_string(): void {
		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, '' );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_json_mapped_money_from_post' );

		$result = $method->invoke(
			$sut,
			$post_id,
			Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY
		);

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_json_mapped_money_from_post
	 * @covers ::log_meta_value_warning
	 */
	public function test_get_json_mapped_money_from_post_with_integer_value(): void {
		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, 12345 );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_json_mapped_money_from_post' );

		$result = $method->invoke(
			$sut,
			$post_id,
			Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY
		);

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_json_mapped_money_from_post
	 * @covers ::log_meta_value_warning
	 */
	public function test_get_json_mapped_money_from_post_with_malformed_json(): void {
		/** @var int $post_id */
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Bitcoin_Address_WP_Post_Interface::POST_TYPE,
			)
		);

		update_post_meta( $post_id, Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY, '{"amount":"100"}' );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_json_mapped_money_from_post' );

		$result = $method->invoke(
			$sut,
			$post_id,
			Bitcoin_Address_WP_Post_Interface::TARGET_AMOUNT_META_KEY
		);

		$this->assertNull( $result );
	}

	/**
	 * @covers ::log_meta_value_warning
	 */
	public function test_log_meta_value_warning_calls_logger_with_correct_parameters(): void {
		$post_id    = 123;
		$meta_key   = 'test_meta_key';
		$meta_value = array( 'invalid' => 'value' );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(
					function ( $message, $context ) use ( $post_id, $meta_key, $meta_value ) {
						$this->assertStringContainsString( 'Failed to parse payment address meta', $message );
						$this->assertEquals( $meta_key, $context['meta_key'] );
						$this->assertEquals( $post_id, $context['post_id'] );
						$this->assertEquals( $meta_value, $context['meta_value'] );
						$this->assertNull( $context['exception'] );
					}
				),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'log_meta_value_warning' );

		$method->invoke( $sut, $post_id, $meta_key, $meta_value );
	}

	/**
	 * @covers ::log_meta_value_warning
	 */
	public function test_log_meta_value_warning_with_exception(): void {
		$post_id    = 456;
		$meta_key   = 'another_meta_key';
		$meta_value = 'invalid string';
		$exception  = new \Exception( 'Test exception' );

		$logger = $this->makeEmpty(
			LoggerInterface::class,
			array(
				'warning' => Expected::once(
					function ( $message, $context ) use ( $post_id, $meta_key, $meta_value, $exception ) {
						$this->assertStringContainsString( 'Failed to parse payment address meta', $message );
						$this->assertEquals( $meta_key, $context['meta_key'] );
						$this->assertEquals( $post_id, $context['post_id'] );
						$this->assertEquals( $meta_value, $context['meta_value'] );
						$this->assertSame( $exception, $context['exception'] );
					}
				),
			)
		);

		$sut = $this->get_sut( logger: $logger );

		$reflection = new \ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'log_meta_value_warning' );

		$method->invoke( $sut, $post_id, $meta_key, $meta_value, $exception );
	}
}
