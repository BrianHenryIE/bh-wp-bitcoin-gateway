<?php
/**
 * @see https://github.com/ietf-wg-httpapi/ratelimit-headers
 * @see https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/
 *
 * 'X-RateLimit-Limit'
 * 'X-RateLimit-Remaining'
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use DateTimeInterface;
use Throwable;

class Rate_Limit_Exception extends BH_WP_Bitcoin_Gateway_Exception {

	/**
	 * Constructor.
	 *
	 * @param ?DateTimeInterface $reset_time When the rate limit will reset, if known.
	 * @param string             $message Exception message.
	 * @param int                $code Exception code.
	 * @param ?Throwable         $previous Previous exception.
	 */
	public function __construct(
		protected ?DateTimeInterface $reset_time = null,
		string $message = '',
		int $code = 0,
		?Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * Get the time when the API can be next used, `null` if unknown.
	 */
	public function get_reset_time(): ?DateTimeInterface {
		return $this->reset_time;
	}
}
