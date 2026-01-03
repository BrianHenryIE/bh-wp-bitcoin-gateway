<?php
/**
 * @see https://github.com/ietf-wg-httpapi/ratelimit-headers
 * @see https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/
 *
 * 'X-RateLimit-Limit'
 * 'X-RateLimit-Remaining'
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use DateTimeInterface;
use Exception;
use Throwable;

class Rate_Limit_Exception extends Exception {

	public function __construct(
		protected ?DateTimeInterface $reset_time = null,
		string $message = '',
		int $code = 0,
		?Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
	}

	public function get_reset_time(): ?DateTimeInterface {
		return $this->reset_time;
	}
}
