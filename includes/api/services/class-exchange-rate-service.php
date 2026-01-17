<?php
/**
 * Fetch & store the current BTC exchange rate; perform currency conversions.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_DateTimeInterface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Helpers\JsonMapper\JsonMapper_Money;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\Rate_Limit_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Exchange_Rate_Service_Result;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\BigDecimal;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\Exception\MathException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Math\RoundingMode;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\BuilderException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Exception\ClassFactoryException;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\FactoryRegistry;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\Handler\PropertyMapper;
use BrianHenryIE\WP_Bitcoin_Gateway\JsonMapper\JsonMapperBuilder;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Uses a `Exchange_Rate_API_Interface` implementation and saves the result, JSON encoded, in a transient.
 */
class Exchange_Rate_Service implements LoggerAwareInterface {
	use LoggerAwareTrait;

	protected const string TRANSIENT_BASE = 'bh_wp_bitcoin_gateway_exchange_rate_';

	/**
	 * Constructor
	 *
	 * @param Exchange_Rate_API_Interface $exchange_rate_api External API to fetch exchange rate data from.
	 * @param LoggerInterface             $logger PSR logger for debug and errors.
	 */
	public function __construct(
		protected Exchange_Rate_API_Interface $exchange_rate_api,
		LoggerInterface $logger,
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Get the value of 1 BTC in the requested currency, hopefully from cache.
	 *
	 * Return the cached exchange rate, or fetch it.
	 * Caches for one hour.
	 *
	 * TODO: Add rate limiting.
	 *
	 * @param Currency $currency The fiat currency to get the BTC exchange rate for (e.g., USD, EUR, GBP).
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the exchange rate API returns invalid data or the currency is not supported.
	 */
	public function get_exchange_rate( Currency $currency ): ?Money {

		$exchange_rate_stored_transient = $this->get_cached_exchange_rate( $currency );

		if ( ! is_null( $exchange_rate_stored_transient ) ) {
			return $exchange_rate_stored_transient->rate;
		}

		try {
			$exchange_rate_service_result = $this->fetch_exchange_rate( $currency );
		} catch ( Rate_Limit_Exception $e ) {
			// TODO: set up background job.
			return null;
		} catch ( UnknownCurrencyException $e ) {
			// Ignore this error.
			// It could only happen if the currency of the Money object passed to the function was not
			// recognised by brick/money which doesn't make sense. I.e. the exception would have happened
			// before this function was called.
			return null;
		} catch ( JsonException $e ) {
			// TODO: decide if this should be logged inside the API class.
			return null;
		}

		return $exchange_rate_service_result->rate;
	}

	/**
	 * Synchronously check the exchange rate; include the previous cached value in the result.
	 *
	 * @param Currency $currency The currency to fetch the exchange rate for.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the request fails.
	 * @throws JsonException If the API response body is not JSON, as expected.
	 * @throws UnknownCurrencyException Almost impossible… it would mean the brick/money library that created the Currency object does not recognise it.
	 */
	public function update_exchange_rate( Currency $currency ): Exchange_Rate_Service_Result {

		$previous                                 = $this->get_cached_exchange_rate( $currency );
		$updated                                  = (array) $this->fetch_exchange_rate( $currency );
		$updated['previous_cached_exchange_rate'] = $previous;

		// Allow array destructuring in constructor calls.
		return new Exchange_Rate_Service_Result( ...$updated ); // @phpstan-ignore argument.type
	}

	/**
	 * Synchronously check (then cache) the exchange rate.
	 *
	 * @param Currency $currency The currency to fetch the exchange rate for.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception If the request fails.
	 * @throws JsonException If the API response body is not JSON, as expected.
	 * @throws UnknownCurrencyException Almost impossible… it would mean the brick/money library that created the Currency object does not recognise it.
	 */
	protected function fetch_exchange_rate( Currency $currency ): Exchange_Rate_Service_Result {
		$exchange_rate_money = $this->exchange_rate_api->get_exchange_rate( $currency );

		return $this->set_cached_exchange_rate(
			rate: $exchange_rate_money,
			api_classname: get_class( $this->exchange_rate_api )
		);
	}

	/**
	 * Get the BTC value of another currency amount.
	 *
	 * Limited currency support: 'USD'|'EUR'|'GBP', maybe others.
	 *
	 * @param Money $fiat_amount The order total amount in fiat currency from the WooCommerce order (stored as a float string in order meta).
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When no exchange rate is available for the given currency.
	 * @throws UnknownCurrencyException If somehow the currency requested in the Money `$fiat_amount` parameter doesn't exist.
	 * @throws MathException If we attempt to divide by zero.
	 */
	public function convert_fiat_to_btc( Money $fiat_amount ): Money {

		$exchange_rate = $this->get_exchange_rate( $fiat_amount->getCurrency() );

		if ( is_null( $exchange_rate ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'No exchange rate available' );
		}

		// 1 BTC = xx USD.
		$exchange_rate = BigDecimal::of( '1' )->dividedBy( $exchange_rate->getAmount(), 24, RoundingMode::HALF_EVEN );

		return $fiat_amount->convertedTo( Currency::of( 'BTC' ), $exchange_rate, null, RoundingMode::HALF_EVEN );
	}

	/**
	 * E.g. `bh_wp_bitcoin_gateway_exchange_rate_USD`.
	 *
	 * @param Currency $currency The currency we have just fetched the exchange rate for.
	 */
	protected function get_transient_name( Currency $currency ): string {
		return self::TRANSIENT_BASE . $currency->getCurrencyCode();
	}

	/**
	 * Save using transients
	 *
	 * Maybe later directly use {@see wp_using_ext_object_cache}, {@see wp_cache_set()}.
	 *
	 * @param Money  $rate Money object where the value in that currency equals one BTC.
	 * @param string $api_classname The API that was used to fetch the exchange rate.
	 */
	protected function set_cached_exchange_rate(
		Money $rate,
		string $api_classname,
	): Exchange_Rate_Service_Result {

		$exchange_rate = new Exchange_Rate_Service_Result(
			rate: $rate,
			api_classname: $api_classname,
			date_saved: new DateTimeImmutable(),
		);

		// This returns a bool. If setting transients is failing, we should broadly rate limit trying anything.
		set_transient(
			transient: $this->get_transient_name(
				currency: $rate->getCurrency()
			),
			value: wp_json_encode( $exchange_rate ),
			expiration: HOUR_IN_SECONDS
		);

		return $exchange_rate;
	}

	/**
	 * Get the JSON encoded transient, parse it to an object.
	 *
	 * @param Currency $currency The currency to fetch (mostly for the transient name).
	 */
	protected function get_cached_exchange_rate( Currency $currency ): ?Exchange_Rate_Service_Result {

		/** @var false|string $exchange_rate_stored_transient_json_string */
		$exchange_rate_stored_transient_json_string = get_transient(
			transient: $this->get_transient_name( $currency )
		);

		if ( ! is_string( $exchange_rate_stored_transient_json_string ) ) {
			return null;
		}

		try {
			$factory_registry = new FactoryRegistry();

			$factory_registry->addFactory(
				DateTimeInterface::class,
				new JsonMapper_DateTimeInterface()
			);

			$factory_registry->addFactory(
				Money::class,
				new JsonMapper_Money()
			);
		} catch ( ClassFactoryException $exception ) {
			// Something must be wrong with the factory implementation.
			// Definitely should not be a run-time error to just register them.
			$this->logger->error( $exception->getMessage(), array( 'exception' => $exception ) );
			return null;
		}

		// TODO: after testing, see what -> are unnecessary.
		$property_mapper = new PropertyMapper( $factory_registry );

		try {
			$mapper = JsonMapperBuilder::new()
				->withPropertyMapper( $property_mapper )
				->withAttributesMiddleware()
				->withDocBlockAnnotationsMiddleware()
				->withTypedPropertiesMiddleware()
				->withNamespaceResolverMiddleware()
				->withObjectConstructorMiddleware( $factory_registry )
				->build();
		} catch ( BuilderException $exception ) {
			// NB Just catching this / hiding it could result in constant API calls.
			// So...? When there's an API error that's not external, we should rate limit internally somehow.
			$this->logger->error( $exception->getMessage(), array( 'exception' => $exception ) );
			return null;
		}

		/** @var Exchange_Rate_Service_Result $exchange_rate */
		$exchange_rate = $mapper->mapToClassFromString(
			$exchange_rate_stored_transient_json_string,
			Exchange_Rate_Service_Result::class
		);

		return $exchange_rate;
	}
}
