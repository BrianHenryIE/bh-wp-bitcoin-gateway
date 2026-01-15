<?php
/**
 * Claude generated. Not great, had to fix a lot.
 *
 * `{"date":"2024-01-15 14:30:00.000000","timezone_type":3,"timezone":"UTC"}`.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Some errors ignored in {@see /phpstan.neon}.
 *
 * @coversDefaultClass \BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_DateTimeInterface
 */
class JsonMapper_DateTimeInterface_Unit_Test extends \Codeception\Test\Unit {

	/**
	 * Test that a valid JSON object with date and timezone is successfully converted to DateTimeInterface.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_invoke_with_valid_data(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '2024-01-15 14:30:00';
		$timezone_string = 'America/New_York';

		$datetime = new DateTimeImmutable(
			datetime: $datetime_string,
			timezone: new DateTimeZone(
				timezone: $timezone_string,
			)
		);

		$json_object = json_decode( json_encode( $datetime ) );

		$result = $mapper( $json_object );

		$this->assertEquals( '2024-01-15 14:30:00', $result->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( 'America/New_York', $result->getTimezone()->getName() );
	}

	/**
	 * Test that timezone conversion is properly applied.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_timezone_is_properly_set(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '2024-01-15 14:30:00';
		$timezone_string = 'UTC';

		$json_object = json_decode(
			json_encode(
				new DateTimeImmutable(
					datetime: $datetime_string,
					timezone: new DateTimeZone(
						timezone: $timezone_string,
					)
				)
			)
		);

		$result = $mapper( $json_object );

		$this->assertEquals( 'UTC', $result->getTimezone()->getName() );
	}

	/**
	 * Test that ISO 8601 formatted dates are handled correctly.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_iso_8601_date_format(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '2024-01-15T14:30:00+00:00';
		$timezone_string = 'UTC';

		$json_object = json_decode(
			json_encode(
				new DateTimeImmutable(
					datetime: $datetime_string,
					timezone: new DateTimeZone(
						timezone: $timezone_string,
					)
				)
			)
		);

		$result = $mapper( $json_object );

		$this->assertInstanceOf( \DateTimeInterface::class, $result );
		$this->assertEquals( '2024-01-15', $result->format( 'Y-m-d' ) );
	}

	/**
	 * Test that missing 'date' property throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_missing_date_property_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$timezone_string = 'UTC';

		$json_object = json_decode(
			sprintf( '{"timezone_type":3,"timezone":"%s"}', $timezone_string )
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded DateTime object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that missing 'timezone' property throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_missing_timezone_property_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '2024-01-15 14:30:00';

		$as = json_encode(
			new DateTimeImmutable(
				datetime: $datetime_string
			)
		);

		$json_object = json_decode(
			sprintf( '{"date":"%s"}', $datetime_string )
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded DateTime object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that non-string 'date' property throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_non_string_date_property_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_not_string = 12345;
		$timezone_string     = 'UTC';

		$datetime_like_object           = new class(){};
		$datetime_like_object->date     = $datetime_not_string;
		$datetime_like_object->timezone = $timezone_string;

		$json_object = json_decode(
			json_encode(
				$datetime_like_object
			)
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded DateTime object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that non-string 'timezone' property throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_non_string_timezone_property_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string      = '2024-01-15 14:30:00';
		$timezone__not_string = array( 'UTC' );

		$datetime_like_object           = new class(){};
		$datetime_like_object->date     = $datetime_string;
		$datetime_like_object->timezone = $timezone__not_string;

		$json_object = json_decode(
			json_encode(
				$datetime_like_object
			)
		);

		$this->expectException( BH_WP_Bitcoin_Gateway_Exception::class );
		$this->expectExceptionMessage( 'Invalid json encoded DateTime object.' );

		$mapper( $json_object );
	}

	/**
	 * Test that malformed date string throws DateMalformedStringException.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_malformed_date_string_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = 'invalid-date-string';
		$timezone_string = 'UTC';

		$json_object = json_decode(
			sprintf(
				'{"date":"%s","timezone_type":3,"timezone":"%s"}',
				$datetime_string,
				$timezone_string
			)
		);

		$this->expectException( DateMalformedStringException::class );

		$mapper( $json_object );
	}

	/**
	 * Test that invalid timezone string throws DateInvalidTimeZoneException.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_invalid_timezone_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '2024-01-15 14:30:00';
		$timezone_string = 'Invalid/Timezone';

		$json_object = json_decode(
			sprintf(
				'{"date":"%s","timezone_type":1,"timezone":"%s"}',
				$datetime_string,
				$timezone_string
			)
		);

		$this->expectException( DateInvalidTimeZoneException::class );

		$mapper( $json_object );
	}

	/**
	 * Test various valid date formats.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_various_valid_date_formats(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$date_formats = array(
			'2024-01-15',
			'2024-01-15 14:30:00',
			'January 15, 2024',
			'15-01-2024',
			'now',
		);

		foreach ( $date_formats as $date_format ) {
			$datetime_string = $date_format;
			$timezone_string = 'UTC';

			$json_object = json_decode(
				json_encode(
					new DateTimeImmutable(
						datetime: $datetime_string,
						timezone: new DateTimeZone(
							timezone: $timezone_string,
						)
					)
				)
			);

			$result = $mapper( $json_object );

			$this->assertInstanceOf( \DateTimeInterface::class, $result, "Failed for date format: {$date_format}" );
		}
	}

	/**
	 * Test various valid timezones.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_various_valid_timezones(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$timezones = array(
			'UTC',
			'America/New_York',
			'Europe/London',
			'Asia/Tokyo',
			'Australia/Sydney',
			'Pacific/Auckland',
		);

		foreach ( $timezones as $timezone ) {
			$datetime_string = '2024-01-15 14:30:00';
			$timezone_string = $timezone;

			$json_object = json_decode(
				json_encode(
					new DateTimeImmutable(
						datetime: $datetime_string,
						timezone: new DateTimeZone(
							timezone: $timezone_string,
						)
					)
				)
			);

			$result = $mapper( $json_object );

			$this->assertEquals( $timezone, $result->getTimezone()->getName(), "Failed for timezone: {$timezone}" );
		}
	}

	/**
	 * Test that the returned object is immutable.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_returns_immutable_datetime(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '2024-01-15 14:30:00';
		$timezone_string = 'UTC';

		$json_object = json_decode(
			json_encode(
				new DateTimeImmutable(
					datetime: $datetime_string,
					timezone: new DateTimeZone(
						timezone: $timezone_string,
					)
				)
			)
		);

		$result = $mapper( $json_object );

		$this->assertInstanceOf( DateTimeImmutable::class, $result );

		$modified = $result->modify( '+1 day' );
		$this->assertNotSame( $result, $modified );
		$this->assertEquals( '2024-01-15', $result->format( 'Y-m-d' ) );
		$this->assertEquals( '2024-01-16', $modified->format( 'Y-m-d' ) );
	}

	/**
	 * Test empty string properties throw validation exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_empty_string_date_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '';
		$timezone_string = '+00:00';

		$json_object = json_decode(
			sprintf(
				'{"date":"%s","timezone_type":1,"timezone":"%s"}',
				$datetime_string,
				$timezone_string
			)
		);

		$this->expectException( DateMalformedStringException::class );

		try {
			$mapper( $json_object );
		} catch ( BH_WP_Bitcoin_Gateway_Exception $exception ) {
			throw $exception->getPrevious();
		}
	}

	/**
	 * Test empty string timezone throws exception.
	 *
	 * @covers ::__invoke
	 * @covers ::validate
	 */
	public function test_empty_string_timezone_throws_exception(): void {
		$mapper = new JsonMapper_DateTimeInterface();

		$datetime_string = '2024-01-15 14:30:00';
		$timezone_string = ''; // If it wasn't computer generated, it could be ignored.

		$json_object = json_decode(
			sprintf(
				'{"date":"%s","timezone_type":3,"timezone":"%s"}',
				$datetime_string,
				$timezone_string
			)
		);

		$this->expectException( DateInvalidTimeZoneException::class );

		try {
			$mapper( $json_object );
		} catch ( BH_WP_Bitcoin_Gateway_Exception $exception ) {
			throw $exception->getPrevious();
		}
	}
}
