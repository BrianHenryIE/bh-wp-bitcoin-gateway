<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Payment_Service
 */
class Payment_Service_Unit_Test extends \Codeception\Test\Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		/** @phpstan-ignore argument.type */
		WP_Mock::passthruFunction( 'wp_json_encode', array( 'return' => fn( $value ) => json_encode( $value ) ) );
	}

	protected function tearDown(): void {
		parent::tearDown();
		WP_Mock::tearDown();
	}

	protected function get_sut(
		?Blockchain_API_Interface $blockchain_api = null,
		?Bitcoin_Transaction_Repository $bitcoin_transaction_repository = null,
		?LoggerInterface $logger = null,
	): Payment_Service {
		return new Payment_Service(
			blockchain_api: $blockchain_api ?? $this->makeEmpty( Blockchain_API_Interface::class ),
			bitcoin_transaction_repository: $bitcoin_transaction_repository ?? $this->makeEmpty( Bitcoin_Transaction_Repository::class ),
			logger: $logger ?? new ColorLogger(),
		);
	}

	/**
	 * Test get_blockchain_height when no saved value exists.
	 *
	 * Scenario 1: No saved value – assert api function is called, value is saved, value from api is returned
	 *
	 * @covers ::get_blockchain_height
	 */
	public function test_get_blockchain_height_no_saved_value(): void {

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_blockchain_height' => Expected::once( 800000 ),
			)
		);

		$sut = $this->get_sut(
			blockchain_api: $blockchain_api,
		);

		WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_blockchain_height' ),
				'return' => false,
			)
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
			)
		);

		$reflection = new ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_blockchain_height' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut );

		$this->assertEquals( 800000, $result );
	}

	/**
	 * Test get_blockchain_height when valid saved value with recent time exists.
	 *
	 * Scenario 2: Valid saved value (json) with recent time – assert api function is not called, save is not called, value is returned
	 *
	 * @covers ::get_blockchain_height
	 */
	public function test_get_blockchain_height_valid_recent_saved_value(): void {

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_blockchain_height' => Expected::never(),
			)
		);

		$sut = $this->get_sut(
			blockchain_api: $blockchain_api,
		);

		$recent_time  = new DateTimeImmutable( 'now' );
		$saved_height = 800000;
		$saved_value  = json_encode(
			array(
				'blockchain_height' => $saved_height,
				'time'              => $recent_time->format( DateTimeInterface::ATOM ),
			)
		);

		WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_blockchain_height' ),
				'return' => $saved_value,
			)
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 0,
			)
		);

		$reflection = new ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_blockchain_height' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut );

		$this->assertEquals( $saved_height, $result );
	}

	/**
	 * Test get_blockchain_height when valid saved value with outdated time exists.
	 *
	 * Scenario 3: Valid saved value (json) with outdated time – assert api function is called, value is saved, value from api is returned
	 *
	 * @covers ::get_blockchain_height
	 */
	public function test_get_blockchain_height_valid_outdated_saved_value(): void {

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_blockchain_height' => Expected::once( 800001 ),
			)
		);

		$sut = $this->get_sut(
			blockchain_api: $blockchain_api,
		);

		$outdated_time = new DateTimeImmutable( '-15 minutes' );
		$saved_value   = json_encode(
			array(
				'blockchain_height' => 800000,
				'time'              => $outdated_time->format( DateTimeInterface::ATOM ),
			)
		);

		WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_blockchain_height' ),
				'return' => $saved_value,
			)
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
			)
		);

		$reflection = new ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_blockchain_height' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut );

		$this->assertEquals( 800001, $result );
	}

	/**
	 * Test get_blockchain_height when invalid saved value (array) exists.
	 *
	 * Scenario 4: Invalid saved value array – assert api function is called, value is saved, value from api is returned
	 *
	 * @covers ::get_blockchain_height
	 */
	public function test_get_blockchain_height_invalid_saved_value_array(): void {

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_blockchain_height' => Expected::once( 800002 ),
			)
		);

		$sut = $this->get_sut(
			blockchain_api: $blockchain_api,
		);

		WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_blockchain_height' ),
				'return' => array( 'invalid' => 'array' ),
			)
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
			)
		);

		$reflection = new ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_blockchain_height' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut );

		$this->assertEquals( 800002, $result );
	}

	/**
	 * Test get_blockchain_height when invalid saved value (non-json string) exists.
	 *
	 * Scenario 5: Invalid saved value non-json string – assert api function is called, value is saved, value from api is returned
	 *
	 * @covers ::get_blockchain_height
	 */
	public function test_get_blockchain_height_invalid_saved_value_non_json_string(): void {

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_blockchain_height' => Expected::once( 800003 ),
			)
		);

		$sut = $this->get_sut(
			blockchain_api: $blockchain_api,
		);

		WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_blockchain_height' ),
				'return' => 'not-valid-json',
			)
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
			)
		);

		$reflection = new ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_blockchain_height' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut );

		$this->assertEquals( 800003, $result );
	}

	/**
	 * Test get_blockchain_height when saved value has invalid time format.
	 *
	 * @covers ::get_blockchain_height
	 */
	public function test_get_blockchain_height_invalid_time_format(): void {

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_blockchain_height' => Expected::once( 800004 ),
			)
		);

		$sut = $this->get_sut(
			blockchain_api: $blockchain_api,
		);

		$saved_value = json_encode(
			array(
				'blockchain_height' => 800000,
				'time'              => 'invalid-date-format',
			)
		);

		WP_Mock::userFunction(
			'get_option',
			array(
				'times'  => 1,
				'args'   => array( 'bh_wp_bitcoin_gateway_blockchain_height' ),
				'return' => $saved_value,
			)
		);

		WP_Mock::userFunction(
			'update_option',
			array(
				'times' => 1,
			)
		);

		$reflection = new ReflectionClass( $sut );
		$method     = $reflection->getMethod( 'get_blockchain_height' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut );

		$this->assertEquals( 800004, $result );
	}

	/**
	 * @covers ::update_address_transactions
	 */
	public function test_update_address_transactions(): void {

		$transaction = $this->make(
			Transaction::class,
			array(
				'get_txid'         => 'transaction_from_api',
				'get_block_height' => 123,
				'get_block_time'   => new DateTimeImmutable( 'now' ),
			)
		);

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_transactions_received' => Expected::once(
					function ( string $address ) use ( $transaction ): array {
						assert( 'xpub' === $address );
						return array( $transaction );
					}
				),
			)
		);

		$bitcoin_transaction = $this->make(
			Bitcoin_Transaction::class,
			array(
				'get_txid'    => 'transaction_from_wp_post',
				'get_post_id' => 567,
			)
		);

		$bitcoin_transaction_repository = $this->makeEmpty(
			Bitcoin_Transaction_Repository::class,
			array(
				'save_new' => Expected::once(
					function (
						Transaction_Interface $transaction,
						Bitcoin_Address $address,
					) use ( $bitcoin_transaction ): Bitcoin_Transaction {
						return $bitcoin_transaction;
					}
				),
			)
		);

		$sut = $this->get_sut(
			blockchain_api: $blockchain_api,
			bitcoin_transaction_repository: $bitcoin_transaction_repository
		);

		$address = $this->make(
			Bitcoin_Address::class,
			array(
				'get_raw_address'  => Expected::once( 'xpub' ),
				'set_transactions' => Expected::once(),
				'get_tx_ids'       => Expected::once( array() ),
			)
		);

		$result           = $sut->update_address_transactions( $address );
		$new_transactions = $result->get_new_transactions();
		$result_first     = array_shift(
			$new_transactions
		);

		$this->assertEquals( 'transaction_from_wp_post', $result_first?->get_txid() );
	}
}
