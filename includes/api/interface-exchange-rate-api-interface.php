<?php
/**
 * One Bitcoin equivalent in a given currency.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Exceptions\BH_WP_Bitcoin_Gateway_Exception;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Currency;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use JsonException;

interface Exchange_Rate_API_Interface {

	/**
	 * Given a currency, e.g. USD, return the equivalent of 1 BTC in that currency, e.g. $10,000 USD.
	 *
	 * @param Currency $currency FIAT currency (presumably) e.g. USD.
	 *
	 * @return Money Value of one BTC in that currency.
	 *
	 * @throws BH_WP_Bitcoin_Gateway_Exception When the request fails.
	 * @throws UnknownCurrencyException Almost impossible – unless the Money library cannot use a Currency object it created.
	 * @throws JsonException If the API returns malformed JSON.
	 */
	public function get_exchange_rate( Currency $currency ): Money;
}
