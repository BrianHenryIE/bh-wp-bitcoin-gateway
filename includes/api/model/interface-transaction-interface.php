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
	 * Double SHA-256 hash of the serialized transaction data, byte-reversed (displayed in little-endian format).
	 */
	public function get_txid(): string;

	/**
	 * "1" or "2".
	 */
	public function get_version(): int;

	/**
	 * Used to filter transactions to only those between the time the order was placed, and paid.
	 */
	public function get_block_time(): DateTimeInterface;

	/**
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array;

	/**
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array;

	/**
	 * Returns null for mempool.
	 */
	public function get_block_height(): ?int;
}
