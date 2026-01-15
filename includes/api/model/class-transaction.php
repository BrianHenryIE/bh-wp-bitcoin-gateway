<?php
/**
 * Bitcoin transaction data model.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use DateTimeInterface;

/**
 * Represents a Bitcoin transaction received from blockchain APIs.
 *
 * Contains transaction ID, version, block height/time, and input/output vectors.
 */
readonly class Transaction implements Transaction_Interface {

	/**
	 * Constructor.
	 *
	 * @param string             $tx_id The transaction ID (txid) uniquely identifying this transaction on the Bitcoin blockchain.
	 * @param DateTimeInterface  $block_time The timestamp when the block containing this transaction was mined.
	 * @param int                $version The Bitcoin transaction version number indicating which validation rules apply.
	 * @param Transaction_VIn[]  $v_in Array of transaction inputs (vector-in) showing which previous outputs are being spent.
	 * @param Transaction_VOut[] $v_out Array of transaction outputs (vector-out) showing which addresses received funds and how much.
	 * @param int|null           $block_height The block height in the blockchain where this transaction was confirmed, or null if unconfirmed.
	 */
	public function __construct(
		public string $tx_id,
		public DateTimeInterface $block_time,
		public int $version,
		public array $v_in,
		public array $v_out,
		public ?int $block_height = null,
	) {
	}

	/**
	 * Get the transaction ID (txid/hash).
	 *
	 * @return string The unique transaction identifier on the blockchain.
	 */
	public function get_txid(): string {
		return $this->tx_id;
	}

	/**
	 * Get the Bitcoin protocol version number this transaction was created under.
	 */
	public function get_version(): int {
		return $this->version;
	}

	/**
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array {
		return $this->v_in;
	}

	/**
	 * Get the transaction outputs.
	 *
	 * @return Transaction_VOut[] Array of transaction outputs showing destination addresses and amounts.
	 */
	public function get_v_out(): array {
		return $this->v_out;
	}

	/**
	 * Get the block height where this transaction was confirmed.
	 *
	 * @return ?int The blockchain height, or null if the transaction is unconfirmed (in mempool).
	 */
	public function get_block_height(): ?int {
		return $this->block_height;
	}

	/**
	 * Get the timestamp when the transaction block was mined.
	 *
	 * TODO: Can this be null since `::get_block_time()` can be null?
	 */
	public function get_block_time(): DateTimeInterface {
		return $this->block_time;
	}
}
