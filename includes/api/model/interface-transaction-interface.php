<?php
/**
 *
 * @see https://developer.bitcoin.org/reference/transactions.html
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use DateTimeInterface;

interface Transaction_Interface {

	/**
	 * "Double SHA-256 hash of the serialized transaction data, byte-reversed (displayed in little-endian format)."
	 *
	 * AKA transaction hash.
	 */
	public function get_txid(): string;

	/**
	 * "A version number prefixed to transactions to allow upgrading. Programs creating transactions using newer
	 * consensus rules may use higher version numbers. Version 2 means that BIP 68 applies."
	 *
	 * "1" or "2".
	 */
	public function get_version(): int;

	/**
	 * "Each non-coinbase input spends an outpoint from a previous transaction."
	 *
	 * ?? Will all these generally come from the same master public key but one or more 0/x sub addresses?
	 * ?? But if multiple people send to the same address at the same time could they be in the same transaction because they're in the same block?
	 *
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array;

	/**
	 * ?? Probably the address the btc is sent to and the address of the miner>??
	 *
	 * ?? In reality, this should be
	 *
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array;

	/**
	 * Returns null for mempool.
	 */
	public function get_block_height(): ?int;

	/**
	 * Used to filter transactions to only those between the time the order was placed, and paid.
	 */
	public function get_block_time(): DateTimeInterface;
}
