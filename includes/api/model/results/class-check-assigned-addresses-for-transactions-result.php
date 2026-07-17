<?php
/**
 * Result object for checking a batch of addresses for transactions.
 *
 * Records which addresses were checked (with each address's individual result), which addresses were
 * not checked because the operation stopped early (rate limit / error), and whether a follow-up
 * background job was scheduled to check the remaining addresses.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Results;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet\Bitcoin_Address;
use DateTimeInterface;

/**
 * @used-by API::check_new_addresses_for_transactions()
 * @used-by API::check_addresses_for_transactions()
 */
readonly class Check_Assigned_Addresses_For_Transactions_Result {

	/**
	 * Constructor.
	 *
	 * @param array<string, Update_Address_Transactions_Result> $update_address_transactions_results The per-address results, keyed by raw Bitcoin address, for each address that was successfully checked.
	 * @param array<int, Bitcoin_Address>                       $unchecked_addresses Addresses that were not checked because the operation stopped early (e.g. rate limited).
	 * @param bool                                              $was_follow_up_job_scheduled Whether a background job was scheduled to check the remaining `$unchecked_addresses`.
	 * @param ?DateTimeInterface                                $follow_up_job_time The time the follow-up background job was scheduled for; `null` when no job was scheduled, or when the scheduler's default ("as soon as possible") was used.
	 * @param ?string                                           $incomplete_reason Why the operation stopped early, e.g. the rate limit exception message; `null` when all addresses were checked.
	 */
	public function __construct(
		public array $update_address_transactions_results = array(),
		public array $unchecked_addresses = array(),
		public bool $was_follow_up_job_scheduled = false,
		public ?DateTimeInterface $follow_up_job_time = null,
		public ?string $incomplete_reason = null,
	) {
	}

	/**
	 * The number of addresses successfully checked for transactions before the operation stopped
	 * due to completion, rate limiting, or errors.
	 */
	public function get_checked_addresses_count(): int {
		return count( $this->update_address_transactions_results );
	}

	/**
	 * Were all requested addresses checked?
	 */
	public function is_complete(): bool {
		return array() === $this->unchecked_addresses;
	}
}
