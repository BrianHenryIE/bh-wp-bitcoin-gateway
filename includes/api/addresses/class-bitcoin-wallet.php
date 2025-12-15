<?php
/**
 * Custom post type in WordPress, keyed with GUID of the wallet.
 *
 * TODO: Update the wp_post last modified time when updating metadata.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

class Bitcoin_Wallet implements Bitcoin_Wallet_Interface {

	/**
	 * Constructor
	 */
	public function __construct(
		protected int $post_id,
		protected string $xpub,
		protected Bitcoin_Wallet_Status $status,
		protected ?int $address_index, // null before any addresses have been generated.
		protected ?string $balance,
	) {
	}

	/**
	 * Used when adding this wallet as a parent of a generated address.
	 */
	public function get_post_id(): int {
		return $this->post_id;
	}

	/**
	 * The current status of the wallet.
	 *
	 * TODO: Mark wallets inactive when removed from a gateway.
	 */
	public function get_status(): Bitcoin_Wallet_Status {
		return $this->status;
	}

	/**
	 * Return the xpub/ypub/zpub this wallet represents.
	 */
	public function get_xpub(): string {
		return $this->xpub;
	}

	/**
	 * Get the current balance of this wallet, or null if it has never been checked.
	 *
	 * Must iterate across all addresses and sum them.
	 *
	 * TODO: "balance" is the wrong term. This could mean total received not spent or total received after spending.
	 * TODO: "received" is also ambiguous, is that total-received-for-orders or for the whole wallet? Confirmations?
	 */
	public function get_balance(): ?string {
		return $this->balance;
	}

	/**
	 * Get the index of the last generated address, so generating new addresses can start higher.
	 */
	public function get_address_index(): ?int {
		return $this->address_index;
	}

	/**
	 * Save the index of the highest generated address.
	 *
	 * @param int $index Nth address generated index.
	 */
	public function set_address_index( int $index ): void {

		$this->address_index = $index;

		$bitcoin_wallet_factory    = new Bitcoin_Wallet_Factory();
		$bitcoin_wallet_repository = new Bitcoin_Wallet_Repository( $bitcoin_wallet_factory );

		$bitcoin_wallet_repository->set_highest_address_index( $this, $index );
	}
}
