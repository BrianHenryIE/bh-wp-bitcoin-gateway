<?php
/**
 * Result object for checking all assigned addresses for payment.
 *
 * Records the per-address payment-check results, which addresses were not checked because the
 * operation stopped early (rate limit / error), and whether a follow-up background job was
 * scheduled to check the remaining addresses.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use DateTimeInterface;

/**
 * @used-by API::check_assigned_addresses_for_payment()
 */
readonly class Check_Assigned_Addresses_For_Payment_Result {

	/**
	 * Constructor.
	 *
	 * @param array<string, Check_Address_For_Payment_Result> $check_address_for_payment_results The per-address results, keyed by raw Bitcoin address, for each assigned address that was successfully checked.
	 * @param array<int, Bitcoin_Address>                     $unchecked_addresses Assigned addresses that were not checked because the operation stopped early (e.g. rate limited).
	 * @param bool                                            $was_follow_up_job_scheduled Whether a background job was scheduled to check the remaining `$unchecked_addresses`.
	 * @param ?DateTimeInterface                              $follow_up_job_time The time the follow-up background job was scheduled for; `null` when no job was scheduled, or when the scheduler's default ("as soon as possible") was used.
	 * @param ?string                                         $incomplete_reason Why the operation stopped early, e.g. the rate limit exception message; `null` when all addresses were checked.
	 */
	public function __construct(
		public array $check_address_for_payment_results = array(),
		public array $unchecked_addresses = array(),
		public bool $was_follow_up_job_scheduled = false,
		public ?DateTimeInterface $follow_up_job_time = null,
		public ?string $incomplete_reason = null,
	) {
	}

	/**
	 * The number of assigned addresses successfully checked for payment before the operation
	 * stopped due to completion, rate limiting, or errors.
	 */
	public function get_checked_addresses_count(): int {
		return count( $this->check_address_for_payment_results );
	}

	/**
	 * The subset of results whose address is now paid (confirmed received >= target amount).
	 *
	 * @return array<string, Check_Address_For_Payment_Result> Keyed by raw Bitcoin address.
	 */
	public function get_paid_address_results(): array {
		return array_filter(
			$this->check_address_for_payment_results,
			fn( Check_Address_For_Payment_Result $check_address_for_payment_result ): bool => $check_address_for_payment_result->is_paid
		);
	}

	/**
	 * Were all assigned addresses checked?
	 */
	public function is_complete(): bool {
		return array() === $this->unchecked_addresses;
	}
}
