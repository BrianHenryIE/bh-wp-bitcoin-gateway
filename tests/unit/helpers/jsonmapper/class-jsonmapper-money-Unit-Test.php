<?php
/**
 * Claude generated tests. Decent this time.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Money
 */
class JsonMapper_Money_Unit_Test extends \Codeception\Test\Unit {

	/**
	 * Test that a valid JSON object with amount and currency is successfully converted to Money.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_invoke_with_valid_data(): void {
		$mapper = new JsonMapper_Money();

		$money = Money::of( '100.50', 'USD' );

		/** @var object $json_object */
		$json_object = json_decode( json_encode( $money ) );

		$result = $mapper( $json_object );

		$this->assertInstanceOf( Money::class, $result );
		$this->assertEquals( '100.50', $result->getAmount()->__toString() );
		$this->assertEquals( 'USD', $result->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Test various valid currency codes.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_various_valid_currencies(): void {
		$mapper = new JsonMapper_Money();

		$currencies = array(
			'USD',
			'EUR',
			'GBP',
			'JPY',
			'BTC',
		);

		foreach ( $currencies as $currency ) {
			$money = Money::of( '100', $currency );

			/** @var object $json_object */
			$json_object = json_decode( json_encode( $money ) );

			$result = $mapper( $json_object );

			$this->assertInstanceOf( Money::class, $result, "Failed for currency: {$currency}" );
			$this->assertEquals( $currency, $result->getCurrency()->getCurrencyCode() );
		}
	}

	/**
	 * Test various valid amounts.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_various_valid_amounts(): void {
		$mapper = new JsonMapper_Money();

		$amounts = array(
			'0.01',
			'100.50',
			'1000000',
			'999999.99',
		);

		foreach ( $amounts as $amount ) {
			$money = Money::of( $amount, 'USD' );

			/** @var object $json_object */
			$json_object = json_decode( json_encode( $money ) );

			$result = $mapper( $json_object );

			$this->assertInstanceOf( Money::class, $result, "Failed for amount: {$amount}" );
			// Compare the actual numeric value, as formatting may vary.
			$this->assertTrue( $result->getAmount()->isEqualTo( $amount ), "Failed for amount: {$amount}" );
		}
	}

	/**
	 * Test that missing 'amount' property throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_missing_amount_property_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'currency' => 'USD',
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded money object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that missing 'currency' property throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_missing_currency_property_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'amount' => '100.50',
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded money object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that both properties missing throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_both_properties_missing_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array();

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded money object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that non-string 'currency' property throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_non_string_currency_property_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'amount'   => '100.50',
			'currency' => array( 'USD' ),
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded money object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that both properties being non-strings throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_both_non_string_properties_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'amount'   => array( 100.50 ),
			'currency' => 840,
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded money object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that empty string amount throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_empty_string_amount_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'amount'   => '',
			'currency' => 'USD',
		);

		$this->expectException( \BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\Exception\NumberFormatException::class );

		try {
			$mapper( $json_object );
		} catch ( BH_WP_Bitcoin_Gateway_Exception $exception ) {
			throw $exception->getPrevious();
		}
	}

	/**
	 * Test that empty string currency throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_empty_string_currency_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'amount'   => '100.50',
			'currency' => '',
		);

		$this->expectException( UnknownCurrencyException::class );

		$mapper( $json_object );
	}

	/**
	 * Test that invalid currency code throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_invalid_currency_code_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'amount'   => '100.50',
			'currency' => 'INVALID',
		);

		$this->expectException( UnknownCurrencyException::class );

		$mapper( $json_object );
	}

	/**
	 * Test that invalid amount format throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_invalid_amount_format_throws_exception(): void {
		$mapper = new JsonMapper_Money();

		$json_object = (object) array(
			'amount'   => 'not-a-number',
			'currency' => 'USD',
		);

		$this->expectException( \BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\Exception\NumberFormatException::class );

		try {
			$mapper( $json_object );
		} catch ( BH_WP_Bitcoin_Gateway_Exception $exception ) {
			throw $exception->getPrevious();
		}
	}

	/**
	 * Test Bitcoin amounts with high precision.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_bitcoin_amount_with_high_precision(): void {
		$mapper = new JsonMapper_Money();

		$money = Money::of( '0.00000001', 'BTC' );

		/** @var object $json_object */
		$json_object = json_decode( json_encode( $money ) );

		$result = $mapper( $json_object );

		$this->assertInstanceOf( Money::class, $result );
		$this->assertEquals( 'BTC', $result->getCurrency()->getCurrencyCode() );
	}

	/**
	 * Test zero amount.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_zero_amount(): void {
		$mapper = new JsonMapper_Money();

		$money = Money::of( '0', 'USD' );

		/** @var object $json_object */
		$json_object = json_decode( json_encode( $money ) );

		$result = $mapper( $json_object );

		$this->assertInstanceOf( Money::class, $result );
		$this->assertTrue( $result->getAmount()->isZero() );
	}

	/**
	 * Test negative amounts (if supported by brick/money).
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_negative_amount(): void {
		$mapper = new JsonMapper_Money();

		$money = Money::of( '-100.50', 'USD' );

		/** @var object $json_object */
		$json_object = json_decode( json_encode( $money ) );

		$result = $mapper( $json_object );

		$this->assertInstanceOf( Money::class, $result );
		$this->assertEquals( '-100.50', $result->getAmount()->__toString() );
	}
}
