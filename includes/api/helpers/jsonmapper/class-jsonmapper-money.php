<?php
/**
 * JsonMapper deserializer for Money objects.
 *
 * @see FactoryRegistry::addFactory()
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\Exception\NumberFormatException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

/**
 * @see Money::jsonSerialize()
 */
class JsonMapper_Money {

	/**
	 * Callable function for parsing Money by JsonMapper factory.
	 *
	 * @param object{amount?:string|mixed, currency?:string|mixed} $json_object The JSON object to parse to Money.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the JSON object doesn't contain the expected properties:types.
	 * @throws UnknownCurrencyException If `$json_object->currency` isn't a known brick/money currency.
	 */
	public function __invoke( object $json_object ): Money {

		$this->validate( $json_object );
		/** @var object{amount:string, currency:string} $json_object */

		return Money::of(
			amount: $json_object->amount,
			currency:$json_object->currency
		);
	}

	/**
	 * Confirm the JSON object has the expected keys in their expected types.
	 *
	 * @param object{amount?:string|mixed, currency?:string|mixed} $json_object The JSON string (as object) that JsonMapper has been told will parse to Money.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the JSON object doesn't contain the expected properties:types.
	 */
	protected function validate( object $json_object ): void {
		if (
			property_exists( $json_object, 'amount' )
			&& property_exists( $json_object, 'currency' )
			&& is_numeric( $json_object->amount )
			&& is_string( $json_object->currency )
		) {
			return;
		}

		$previous_exception = null;
		if ( property_exists( $json_object, 'amount' ) && ! is_numeric( $json_object->amount ) ) {
			$previous_exception = new NumberFormatException(
				message: is_string( $json_object->amount ) ? $json_object->amount : ''
			);
		}

		throw new BH_WP_Bitcoin_Gateway_Exception(
			message: 'Invalid json encoded money object.',
			previous: $previous_exception
		);
	}
}
