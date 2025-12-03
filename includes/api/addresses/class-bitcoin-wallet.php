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
	 *
	 * @param Bitcoin_Address[] $fresh_addresses
	 */
	public function __construct(
		protected int $post_id,
		protected Bitcoin_Wallet_Status $status,
		protected string $xpub,
		protected ?string $balance,
		protected array $fresh_addresses,
		protected ?int $address_index,
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
	 *
	 * @return string
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
	 * Find addresses generated from this wallet which are unused and return them as `Bitcoin_Address` objects.
	 *
	 * TODO: Maybe this shouldn't be in here?
	 *
	 * @return Bitcoin_Address[]
	 */
	public function get_fresh_addresses(): array {

		$bitcoin_address_factory    = new Bitcoin_Address_Factory();
		$bitcoin_address_repository = new Bitcoin_Address_Repository( $bitcoin_address_factory );
		return $bitcoin_address_repository->get_addresses(
			new Bitcoin_Address_Query(
				wp_post_parent_id: $this->post_id,
				status: Bitcoin_Address_Status::UNUSED,
			)
		);
	}

	/**
	 * Get the index of the last generated address, so generating new addresses can start higher.
	 */
	public function get_address_index(): int {
		$index = get_post_meta( $this->post_id, Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY, true );
		return is_numeric( $index ) ? intval( $index ) : 0; // Empty string '' will parse to 0, but `intval` doesn't accept just anything.
	}

	/**
	 * Save the index of the highest generated address.
	 *
	 * @param int $index Nth address generated index.
	 */
	public function set_address_index( int $index ): void {
		update_post_meta( $this->post_id, Bitcoin_Wallet_WP_Post_Interface::LAST_DERIVED_ADDRESS_INDEX_META_KEY, $index );
	}
}
