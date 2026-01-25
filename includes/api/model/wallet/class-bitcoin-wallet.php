<?php
/**
 * Bitcoin wallet stored as a WordPress custom post type.
 *
 * Represents a hierarchical deterministic (HD) wallet using an extended public key (xpub/ypub/zpub)
 * that can derive multiple payment addresses for receiving Bitcoin payments.
 *
 * Custom post type in WordPress, keyed with GUID of the wallet.
 *
 * TODO: Update the wp_post last modified time when updating metadata.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Wallet;

use BrianHenryIE\WP_Bitcoin_Gateway\WP_Includes\Post_BH_Bitcoin_Wallet;

/**
 * @see Bitcoin_Wallet_WP_Post_Interface
 * @see Post_BH_Bitcoin_Wallet
 */
class Bitcoin_Wallet implements Bitcoin_Wallet_Interface {

	/**
	 * Constructor
	 *
	 * @param int                                                       $post_id The WordPress post ID where this wallet is stored in the database.
	 * @param string                                                    $xpub The extended public key (xpub/ypub/zpub) used to derive child addresses for this wallet.
	 * @param Bitcoin_Wallet_Status                                     $status The current operational status of the wallet (e.g. active, inactive).
	 * @param ?int                                                      $address_index The highest derivation path index used for generating addresses, or null before any addresses have been generated.
	 * @param ?string                                                   $balance The cached total balance received across all addresses derived from this wallet, or null if never calculated.
	 * @param array<array{integration:class-string, gateway_id:string}> $gateways The list of integration,gateway_id that are using this wallet.
	 */
	public function __construct(
		protected int $post_id,
		protected string $xpub,
		protected Bitcoin_Wallet_Status $status,
		protected ?int $address_index, // null before any addresses have been generated.
		protected ?string $balance, // TODO: just use "received", "balance" requires monitoring all used addresses.
		protected array $gateways,
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
	 * @return array<array{integration:class-string, gateway_id:string}>
	 */
	public function get_associated_gateways_details(): array {
		return $this->gateways;
	}
}
