<?php
/**
 * Saved Bitcoin transaction stored as a WordPress custom post type.
 *
 * TODO: record the API that the transaction was found on.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments;

use DateTimeInterface;

/**
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
 */
// TODO: prefer `readonly class` but PHPUnit won't mock readonly.
// readonly class Bitcoin_Transaction implements Transaction_Interface.

/**
 * Wraps a Transaction object from the blockchain API along with metadata about
 * which Bitcoin addresses in the system are associated with this transaction.
 */
class Bitcoin_Transaction implements Transaction_Interface {

	/**
	 * Constructor
	 *
	 * @param int               $post_id The WordPress post ID for this transaction.
	 * @param Transaction       $transaction The transaction object.
	 * @param array<int,string> $bitcoin_addresses Bitcoin addresses as post_id:bitcoin_address pairs.
	 */
	public function __construct(
		protected int $post_id,
		protected Transaction $transaction,
		protected array $bitcoin_addresses,
	) {
	}

	/**
	 * Get the WordPress post ID for this transaction.
	 *
	 * @return int The post ID.
	 */
	public function get_post_id(): int {
		return $this->post_id;
	}

	/**
	 * Get the transaction ID (txid/hash).
	 */
	public function get_txid(): string {
		return $this->transaction->get_txid();
	}

	/**
	 * Get the block time for this transaction.
	 */
	public function get_block_time(): DateTimeInterface {
		return $this->transaction->get_block_time();
	}

	/**
	 * Get the block height for this transaction. (TODO null if unconfirmed?).
	 */
	public function get_block_height(): ?int {
		return $this->transaction->get_block_height();
	}

	/**
	 * Get the transaction version, "1" or "2".
	 */
	public function get_version(): int {
		return $this->transaction->get_version();
	}

	/**
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array {
		return $this->transaction->get_v_in();
	}

	/**
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array {
		return $this->transaction->get_v_out();
	}
}
