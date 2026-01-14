<?php
/**
 * JsonMapper deserializer for DateTimeInterface objects.
 *
 * @see FactoryRegistry::addFactory()
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * E.g. `{"date":"2026-01-14 05:44:17.084622","timezone_type":3,"timezone":"UTC"}`.
 *
 * The `timezone_type` is more of an informational getter than a property, it is not used in constructing the object.
 */
class JsonMapper_DateTimeInterface {

	/**
	 * Callable function for parsing DateTimeInterface by JsonMapper factory.
	 *
	 * @param object{date?:string|mixed, timezone:string|mixed, timezone_type:int} $json_object The JSON object to parse to DateTimeInterface.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the object fails validation.
	 * @throws DateMalformedStringException If the string provided is invalid.
	 * @throws DateInvalidTimeZoneException If the string provided is invalid.
	 */
	public function __invoke( object $json_object ): DateTimeInterface {

		$this->validate( $json_object );
		/** @var object{date:string, timezone:string} $json_object */

		return new DateTimeImmutable(
			datetime: $json_object->date,
			timezone: new DateTimeZone(
				timezone: $json_object->timezone
			),
		);
	}

	/**
	 * Confirm the JSON object has the expected keys in their expected types.
	 *
	 * @param object{date?:string|mixed, timezone?:string|mixed} $json_object The JSON string (as object) that JsonMapper has been told will parse to DateTimeInterface.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the object does not contain the necessary properties:types.
	 */
	protected function validate( object $json_object ): void {
		if (
			property_exists( $json_object, 'date' )
			&& property_exists( $json_object, 'timezone' )
			&& is_string( $json_object->date )
			&& is_string( $json_object->timezone )
			&& ! empty( $json_object->date )
			&& ! empty( $json_object->timezone )
		) {
			return;
		}

		$previous_exception = null;
		if ( property_exists( $json_object, 'date' ) && empty( $json_object->date ) ) {
			$previous_exception = new DateMalformedStringException(
				message: is_string( $json_object->date ) ? $json_object->date : ''
			);
		}

		if ( property_exists( $json_object, 'timezone' ) && empty( $json_object->timezone ) ) {
			$previous_exception = new DateInvalidTimeZoneException(
				message: is_string( $json_object->timezone ) ? $json_object->timezone : ''
			);
		}

		throw new BH_WP_Bitcoin_Gateway_Exception(
			message: 'Invalid json encoded DateTime object.',
			previous: $previous_exception,
		);
	}
}
