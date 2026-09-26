<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Bitcoin_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Repositories\Bitcoin_Transaction_Repository;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
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
		WP_Mock::passthruFunction( 'wp_json_encode', array( 'return' => json_encode( ... ) ) );
	}

	#[\Override]
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

		$result = $method->invoke( $sut );

		$this->assertEquals( 800004, $result );
	}

	/**
	 * @covers ::update_address_transactions
	 */
	public function test_update_address_transactions(): void {

		$transaction_from_api = new Transaction(
			tx_id: 'transaction_from_api',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 100000000, 'BTC' ),
					scriptpubkey_address: 'raw_address',
				),
			),
			block_height: 123,
		);

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_transactions_received' => Expected::once(
					function ( string $address ) use ( $transaction_from_api ): array {
						assert( 'xpub' === $address );
						return array( $transaction_from_api );
					}
				),
			)
		);

		$transaction_from_wp_post = new Transaction(
			tx_id: 'transaction_from_wp_post',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 100000000, 'BTC' ),
					scriptpubkey_address: 'raw_address',
				),
			),
			block_height: 123,
		);

		$bitcoin_transaction = new Bitcoin_Transaction(
			post_id: 567,
			transaction: $transaction_from_wp_post,
			bitcoin_addresses: array()
		);

		$bitcoin_transaction_repository = $this->makeEmpty(
			Bitcoin_Transaction_Repository::class,
			array(
				'save_new' => Expected::once(
					fn( Transaction_Interface $transaction, Bitcoin_Address $address ): Bitcoin_Transaction => $bitcoin_transaction
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

	/**
	 * Test get_address_confirmed_received with transactions that have enough confirmations.
	 *
	 * @covers ::get_address_confirmed_received
	 */
	public function test_get_address_confirmed_received_with_confirmed_transactions(): void {
		$sut = $this->get_sut();

		$raw_address            = 'bc1qtest123';
		$blockchain_height      = 800100;
		$required_confirmations = 3;

		// Transaction with 5 confirmations (more than required).
		$transaction1 = new Transaction(
			tx_id: 'abc123',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 1, 'BTC' ),
					scriptpubkey_address: $raw_address,
				),
			),
			block_height: 800095,
		);

		$transactions = array( $transaction1 );

		$received = $sut->get_address_confirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );

		$this->assertEquals( '1.00000000', $received->getAmount()->__toString() );
		$this->assertEquals( 'BTC', $received->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Test get_address_confirmed_received with transaction that has exactly required confirmations.
	 *
	 * This tests the boundary condition where confirmations exactly equal the requirement.
	 *
	 * @covers ::get_address_confirmed_received
	 */
	public function test_get_address_confirmed_received_with_exactly_required_confirmations(): void {
		$sut = $this->get_sut();

		$raw_address            = 'bc1qtest123';
		$blockchain_height      = 800100;
		$required_confirmations = 3;

		// Transaction with exactly 3 confirmations.
		$transaction = new Transaction(
			tx_id: 'def456',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( '0.5', 'BTC' ),
					scriptpubkey_address: $raw_address,
				),
			),
			block_height: 800097, // blockchain_height (800100) - block_height (800097) = 3.
		);

		$transactions = array( $transaction );

		$received = $sut->get_address_confirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );

		$this->assertEquals( '0.50000000', $received->getAmount()->__toString() );
		$this->assertEquals( 'BTC', $received->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Test get_address_confirmed_received with transaction that has insufficient confirmations.
	 *
	 * @covers ::get_address_confirmed_received
	 */
	public function test_get_address_confirmed_received_with_insufficient_confirmations(): void {
		$sut = $this->get_sut();

		$raw_address            = 'bc1qtest123';
		$blockchain_height      = 800100;
		$required_confirmations = 3;

		// Transaction with only 2 confirmations (less than required).
		$transaction = new Transaction(
			tx_id: 'ghi789',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 100000000, 'BTC' ),
					scriptpubkey_address: $raw_address,
				),
			),
			block_height: 800098, // blockchain_height (800100) - block_height (800098) = 2.
		);

		$transactions = array( $transaction );

		$received = $sut->get_address_confirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );

		$this->assertEquals( '0.00000000', $received->getAmount()->__toString() );
		$this->assertEquals( 'BTC', $received->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Test get_address_confirmed_received with empty transactions array.
	 *
	 * @covers ::get_address_confirmed_received
	 */
	public function test_get_address_confirmed_received_with_empty_transactions(): void {
		$sut = $this->get_sut();

		$raw_address            = 'bc1qtest123';
		$blockchain_height      = 800100;
		$required_confirmations = 3;
		$transactions           = array();

		$received = $sut->get_address_confirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );

		$this->assertEquals( '0.00000000', $received->getAmount()->__toString() );
		$this->assertEquals( 'BTC', $received->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Test get_address_confirmed_received with multiple transactions of varying confirmation levels.
	 *
	 * @covers ::get_address_confirmed_received
	 */
	public function test_get_address_confirmed_received_with_mixed_confirmations(): void {
		$sut = $this->get_sut();

		$raw_address            = 'bc1qtest123';
		$blockchain_height      = 800100;
		$required_confirmations = 3;

		// Transaction with 10 confirmations (should be included).
		$transaction1 = new Transaction(
			tx_id: 'jkl111',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 1, 'BTC' ),
					scriptpubkey_address: $raw_address,
				),
			),
			block_height: 800090,
		);

		// Transaction with exactly 3 confirmations (should be included).
		$transaction2 = new Transaction(
			tx_id: 'mno222',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( '0.5', 'BTC' ),
					scriptpubkey_address: $raw_address,
				),
			),
			block_height: 800097,
		);

		// Transaction with only 2 confirmations (should NOT be included).
		$transaction3 = new Transaction(
			tx_id: 'pqr333',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( '0.025', 'BTC' ),
					scriptpubkey_address: $raw_address,
				),
			),
			block_height: 800098,
		);

		$transactions = array( $transaction1, $transaction2, $transaction3 );

		$received = $sut->get_address_confirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );

		// Should be 1 + 0.5 = 1.5 BTC (transaction3 excluded due to insufficient confirmations).
		$this->assertEquals( '1.50000000', $received->getAmount()->__toString() );
		$this->assertEquals( 'BTC', $received->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Test get_address_confirmed_received filters transactions to correct address.
	 *
	 * @covers ::get_address_confirmed_received
	 */
	public function test_get_address_confirmed_received_filters_by_address(): void {
		$sut = $this->get_sut();

		$raw_address            = 'bc1qtest123';
		$other_address          = 'bc1qother456';
		$blockchain_height      = 800100;
		$required_confirmations = 3;

		// Transaction to our address.
		$transaction1 = new Transaction(
			tx_id: 'stu444',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 10, 'BTC' ),
					scriptpubkey_address: $raw_address,
				),
			),
			block_height: 800090,
		);

		// Transaction to different address (should not be counted).
		$transaction2 = new Transaction(
			tx_id: 'vwx555',
			block_time: new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( 50, 'BTC' ),
					scriptpubkey_address: $other_address,
				),
			),
			block_height: 800090,
		);

		$transactions = array( $transaction1, $transaction2 );

		$received = $sut->get_address_confirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );

		// Should only count transaction1.
		$this->assertEquals( '10.00000000', $received->getAmount()->__toString() );
		$this->assertEquals( 'BTC', $received->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Mempool transactions (no block height) and transactions with too few confirmations count as unconfirmed;
	 * confirmed ones do not.
	 *
	 * @covers ::get_address_unconfirmed_received
	 */
	public function test_get_address_unconfirmed_received_sums_mempool_and_recent_transactions(): void {
		$sut = $this->get_sut();

		$raw_address            = 'bc1qtest123';
		$blockchain_height      = 800100;
		$required_confirmations = 3;

		$make_transaction = fn( string $tx_id, ?int $block_height, string $amount, string $to = 'bc1qtest123' ) => new Transaction(
			tx_id: $tx_id,
			block_time: is_null( $block_height ) ? null : new DateTimeImmutable( 'now' ),
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( $amount, 'BTC' ),
					scriptpubkey_address: $to,
				),
			),
			block_height: $block_height,
		);

		$transactions = array(
			$make_transaction( 'mempool', null, '0.5' ),
			$make_transaction( 'one-confirmation', 800099, '0.25' ),
			$make_transaction( 'confirmed', 800090, '1' ),
			$make_transaction( 'someone-else', null, '9', 'bc1qother' ),
		);

		$unconfirmed = $sut->get_address_unconfirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );
		$confirmed   = $sut->get_address_confirmed_received( $raw_address, $blockchain_height, $required_confirmations, $transactions );

		$this->assertEquals( '0.75000000', $unconfirmed->getAmount()->__toString() );
		$this->assertEquals( '1.00000000', $confirmed->getAmount()->__toString() );
	}

	/**
	 * @covers ::check_address_for_payment
	 */
	public function test_check_address_for_payment_reports_awaiting_confirmation(): void {

		$address = $this->makeEmpty(
			Bitcoin_Address::class,
			array(
				'get_raw_address'   => 'bc1qtest123',
				'get_target_amount' => Money::of( '0.5', 'BTC' ),
				'get_tx_ids'        => null,
			)
		);

		$mempool_transaction = new Transaction(
			tx_id: 'mempool',
			block_time: null,
			version: 1,
			v_in: array(),
			v_out: array(
				new Transaction_VOut(
					value: Money::of( '0.5', 'BTC' ),
					scriptpubkey_address: 'bc1qtest123',
				),
			),
			block_height: null,
		);

		$saved_transaction = new Bitcoin_Transaction(
			post_id: 1,
			transaction: $mempool_transaction,
			bitcoin_addresses: array(),
		);

		$blockchain_api = $this->makeEmpty(
			Blockchain_API_Interface::class,
			array(
				'get_transactions_received' => Expected::once( array( $mempool_transaction ) ),
				'get_blockchain_height'     => 800100,
			)
		);
		$repository     = $this->makeEmpty(
			Bitcoin_Transaction_Repository::class,
			array(
				'save_new' => Expected::once( $saved_transaction ),
			)
		);

		WP_Mock::userFunction( 'get_transient', array( 'return' => false ) );
		WP_Mock::userFunction( 'set_transient', array( 'return' => true ) );

		$sut = $this->get_sut( blockchain_api: $blockchain_api, bitcoin_transaction_repository: $repository );

		$result = $sut->check_address_for_payment( $address );

		$this->assertFalse( $result->is_paid() );
		$this->assertTrue( $result->is_awaiting_confirmation() );
		$this->assertEquals( '0.50000000', $result->get_unconfirmed_received()->getAmount()->__toString() );
	}
}
