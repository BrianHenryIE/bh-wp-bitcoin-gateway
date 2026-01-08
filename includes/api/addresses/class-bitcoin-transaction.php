<?php
/**
 *
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Addresses;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use DateTimeInterface;

/**
 * @phpstan-type WpUpdatePostArray array{ID?: int, post_author?: int, post_date?: string, post_date_gmt?: string, post_content?: string, post_content_filtered?: string, post_title?: string, post_excerpt?: string}
 */
// TODO: prefer `readonly class` but PHPUnit won't mock readonly.
// readonly class Bitcoin_Transaction implements Transaction_Interface {
class Bitcoin_Transaction implements Transaction_Interface {

	/**
	 * Constructor
	 *
	 * @param array<int,string> $bitcoin_addresses post_id:bitcoin_address
	 */
	public function __construct(
		protected int $post_id,
		protected Transaction $transaction,
		protected array $bitcoin_addresses,
	) {
	}

	public function get_post_id(): int {
		return $this->post_id;
	}

	public function get_txid(): string {
		return $this->transaction->get_txid();
	}

	public function get_block_time(): DateTimeInterface {
		return $this->transaction->get_block_time();
	}

	public function get_block_height(): ?int {
		return $this->transaction->get_block_height();
	}


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
