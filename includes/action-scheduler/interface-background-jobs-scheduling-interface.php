<?php
/**
 * Functions implemented by Background_Jobs class, used by API class to schedule jobs.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Action_Scheduler;

use DateTimeInterface;

interface Background_Jobs_Scheduling_Interface {

	public function schedule_generate_new_addresses(): void;

	public function schedule_check_newly_generated_bitcoin_addresses_for_transactions( ?DateTimeInterface $datetime = null ): void;

	public function schedule_check_assigned_bitcoin_address_for_transactions(): void;

	public function schedule_check_for_assigned_addresses_repeating_action(): void;
}
