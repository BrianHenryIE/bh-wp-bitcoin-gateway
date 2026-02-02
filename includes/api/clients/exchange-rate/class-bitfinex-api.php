<?php
/**
 * Bitfinex exchange rate API client.
 *
 * Use the {@see https://bitfinex.com} API for currency conversion.
 *
 * TODO: surface the ToS to the admin UI.
 *
 * @see https://www.bitfinex.com/legal/general/api-terms/
 *
 * @see https://docs.bitfinex.com/docs/rest-public
 * @see https://docs.bitfinex.com/v2/reference#rest-public-ticker
 *
 * @see https://api-pub.bitfinex.com/v2/conf/pub:list:pair:exchange
 *
 * Obviously, this should be spun into its own Composer package and use JsonMapper.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Exchange_Rate_API_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use JsonException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Queries API and returns Money object where 1 BTC = value of requested currency.
 */
class Bitfinex_API implements Exchange_Rate_API_Interface {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Debug logging API calls; error logging failures.
	 */
	public function __construct(
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Fetch the current exchange from a remote API.
	 *
	 * @param Currency $currency The currency to get the Bitcoin exchange rate for.
	 * @return Money The exchange rate.
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the request fails.
	 * @throws UnknownCurrencyException Almost impossible – unless the Money library cannot use a Currency object it created.
	 * @throws JsonException If the API returns unexpected/invalid data.
	 */
	public function get_exchange_rate( Currency $currency ): Money {

		$trading_pair = 'tBTC' . strtoupper( $currency );

		$url = "https://api-pub.bitfinex.com/v2/tickers?symbols={$trading_pair}";

		$request_response = wp_remote_get( $url );

		if ( is_wp_error( $request_response ) ) {
			throw new BH_WP_Bitcoin_Gateway_Exception( $request_response->get_error_message() );
		}

		if ( 200 !== $request_response['response']['code'] ) {
			throw new BH_WP_Bitcoin_Gateway_Exception();
		}

		/**
		 * @var array{0:array{0:string,1:int,2:float,3:int,4:float,5:int,6:float,7:int,8:float,9:int,10:int}} $response_body
		 */
		$response_body = json_decode( (string) $request_response['body'], true, 512, JSON_THROW_ON_ERROR );

		// Multiple rates can be queried at the same time.

		/**
		 * SYMBOL                string The symbol of the requested ticker data,
		 * BID                   float  Price of last highest bid,
		 * BID_SIZE              float  Sum of the 25 highest bid sizes,
		 * ASK                   float  Price of last lowest ask,
		 * ASK_SIZE              float  Sum of the 25 lowest ask sizes,
		 * DAILY_CHANGE          float  Amount that the last price has changed since yesterday,
		 * DAILY_CHANGE_RELATIVE float  Relative price change since yesterday (*100 for percentage change),
		 * LAST_PRICE            float  Price of the last trade,
		 * VOLUME                float  Daily volume,
		 * HIGH                  float  Daily high,
		 * LOW                   float  Daily low
		 */
		$trading_pair_response = $response_body[0];

		$exchange_rate = $trading_pair_response[7];

		return Money::of( $exchange_rate, $currency );
	}
}
