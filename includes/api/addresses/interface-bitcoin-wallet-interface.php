<?php
/**
 * Functions required by the core plugin for any Bitcoin_Wallet instance.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

interface Bitcoin_Wallet_Interface {

	/**
	 * Used when adding this wallet as a parent of a generated address.
	 */
	public function get_post_id(): int;

	/**
	 * The current status of the wallet.
	 *
	 * TODO: Mark wallets inactive when removed from a gateway.
	 */
	public function get_status(): Bitcoin_Wallet_Status;

	/**
	 * Return the xpub/ypub/zpub this wallet represents.
	 */
	public function get_xpub(): string;

	/**
	 * Get the current balance of this wallet, or null if it has never been checked.
	 *
	 * Must iterate across all addresses and sum them.
	 */
	public function get_balance(): ?string;

	/**
	 * Get the index of the last generated address, so generating new addresses can start higher.
	 */
	public function get_address_index(): ?int;
}
