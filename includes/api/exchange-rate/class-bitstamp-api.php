<?php
/**
 * Bitstamp exchange rate API client.
 *
 * @see https://www.bitstamp.net/api/
 *
 * Rate limit is "8000 requests per 10 minutes".
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Queries API and returns Money object where 1 BTC = value of requested currency.
 */
class Bitstamp_API implements Exchange_Rate_API_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance for logging API calls.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->setLogger( $logger );
	}

	/**
	 * Fetch the current exchange from a remote API.
	 *
	 * @param Currency $currency The currency to get the Bitcoin exchange rate for.
	 * @return Money The exchange rate.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the currency is not supported (only USD, EUR, GBP are supported) or when the API request fails.
	 */
	public function get_exchange_rate( Currency $currency ): Money {

		$between = strtolower( "btc{$currency}" );

		$valid_exchanges = array( 'btcusd', 'btceur', 'btcgbp' );

		if ( ! in_array( $between, $valid_exchanges, true ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( 'Bitstamp only supports USD, EUR and GBP.' );
		}

		$url = "https://www.bitstamp.net/api/v2/ticker/{$between}/";

		$request_response = wp_remote_get( $url );

		if ( is_wp_error( $request_response ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception();
		}

		if ( 200 !== $request_response['response']['code'] ) {
			throw new BH_WP_Bitcoin_Gateway_Exception();
		}

		/**
		 * Response array.
		 *
		 * `last`      Last BTC price.
		 * `high`      Last 24 hours price high.
		 * `low`       Last 24 hours price low.
		 * `vwap`      Last 24 hours volume weighted average price.
		 * `volume`    Last 24 hours volume.
		 * `bid`       Highest buy order.
		 * `ask`       Lowest sell order.
		 * `timestamp` Unix timestamp date and time.
		 * `open`      First price of the day.
		 *
		 * // TODO: is `last` or `vwap` the correct value to use?
		 *
		 * @var array{timestamp:string, open:string, high:string, low:string, last:string, volume:string, vwap:string, bid:string, ask:string, side:string, open_24:string, percent_change_24:string, market_type:string} $response
		 */
		$response = json_decode( $request_response['body'], true, 512, JSON_THROW_ON_ERROR );

		return Money::of( $response['last'], $currency );
	}
}
