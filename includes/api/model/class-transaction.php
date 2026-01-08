<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use DateTimeImmutable;
use DateTimeInterface;

class Transaction implements Transaction_Interface {

	/**
	 * @param Transaction_VIn[]  $v_in
	 * @param Transaction_VOut[] $v_out
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

	public function get_txid(): string {
		return $this->tx_id;
	}

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
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array {
		return $this->v_out;
	}

	public function get_block_height(): ?int {
		return $this->block_height;
	}

	public function get_block_time(): DateTimeInterface {
		return $this->block_time;
	}
}
