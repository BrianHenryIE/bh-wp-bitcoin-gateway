<?php
/**
 * Object for recording/caching the API response for an exchange rate.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeInterface;

/**
 * @used-by Exchange_Rate_Service
 */
readonly class Exchange_Rate_Service_Result {

	/**
	 * Constructor
	 *
	 * @param Money                         $rate The value of a currency that equates to 1 BTC.
	 * @param string                        $api_classname The API the exchange rate was fetched from.
	 * @param DateTimeInterface             $date_saved When it was queried/saved.
	 * @param ?Exchange_Rate_Service_Result $previous_cached_exchange_rate The last saved rate/api_classname/date_saved, if any.
	 */
	public function __construct(
		public Money $rate,
		public string $api_classname,
		public DateTimeInterface $date_saved,
		public ?Exchange_Rate_Service_Result $previous_cached_exchange_rate = null,
	) {
	}
}
