<?php
/**
 * Object for recording/caching the API response for an exchange rate.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeInterface;

/**
 * @used-by Exchange_Rate_Service
 */
class Exchange_Rate {

	/**
	 * Constructor
	 *
	 * @param Money $rate The value of a currency that equates to 1 BTC.
	 * @param string $api_classname The API the exchange rate was fetched from.
	 * @param DateTimeInterface $date_saved When it was queried/saved.
	 */
	public function __construct(
		public readonly Money             $rate,
		public readonly string            $api_classname,
		public readonly DateTimeInterface $date_saved,
	) {
	}
}
