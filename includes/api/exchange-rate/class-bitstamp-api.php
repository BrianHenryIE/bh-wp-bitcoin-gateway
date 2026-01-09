<?php
/**
 * @see https://www.bitstamp.net/api/
 *
 * Rate limit is "8000 requests per 10 minutes".
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Bitstamp_API implements Exchange_Rate_API_Interface {
	use LoggerAwareTrait;

	public function __construct( LoggerInterface $logger ) {
		$this->setLogger( $logger );
	}

	/**
	 * Fetch the current exchange from a remote API.
	 */
	public function get_exchange_rate( Currency $currency ): Money {

		$between = strtolower( "btc{$currency}" );

		$valid_exchanges = array( 'btcusd', 'btceur', 'btcgbp' );

		if ( ! in_array( $between, $valid_exchanges, true ) ) {
			throw new \Exception( 'Bitstamp only supports USD, EUR and GBP.' );
		}

		$url = "https://www.bitstamp.net/api/v2/ticker/{$between}/";

		$request_response = wp_remote_get( $url );

		if ( is_wp_error( $request_response ) ) {
			throw new \Exception();
		}

		if ( 200 !== $request_response['response']['code'] ) {
			throw new \Exception();
		}

		/**
		 * last      Last BTC price.
		 * high      Last 24 hours price high.
		 * low       Last 24 hours price low.
		 * vwap      Last 24 hours volume weighted average price.
		 * volume    Last 24 hours volume.
		 * bid       Highest buy order.
		 * ask       Lowest sell order.
		 * timestamp Unix timestamp date and time.
		 * open      First price of the day.
		 *
		 * // TODO: is `last` correct?
		 *
		 * @var array{timestamp:string, open:string, high:string, low:string, last:string, volume:string, vwap:string, bid:string, ask:string, side:string, open_24:string, percent_change_24:string, market_type:string} $response
		 */
		$response = json_decode( $request_response['body'], true );

		return Money::of( $response['last'], $currency );
	}
}
