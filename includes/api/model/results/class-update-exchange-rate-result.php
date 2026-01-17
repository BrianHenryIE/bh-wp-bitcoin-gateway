<?php
/**
 * Details the requested exchange rate currency and the updated value.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler\Background_Jobs_Actions_Handler;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Results\Exchange_Rate_Service_Result;

/**
 * Exchange rate update result model.
 *
 * @used-by API::update_exchange_rate()
 * @see Background_Jobs_Actions_Handler::update_exchange_rate()
 */
readonly class Update_Exchange_Rate_Result extends Exchange_Rate_Service_Result {

	/**
	 * Constructor
	 *
	 * @param string                       $requested_exchange_rate_currency The currency as string, "USD" etc.
	 * @param string                       $source Where the currency to query was determined.
	 * @param Exchange_Rate_Service_Result $updated_exchange_rate The rate/classname/date/previous from the service.
	 */
	public function __construct(
		public string $requested_exchange_rate_currency,
		public string $source,
		public Exchange_Rate_Service_Result $updated_exchange_rate,
	) {
		parent::__construct( ...(array) $updated_exchange_rate );
	}
}
