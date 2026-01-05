<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\TXSummary;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\VOut;
use DateTimeInterface;

/**
 * Adapter that accepts a `BtcRpcExplorer\Model\TXSummary` and provides `API\Model\Transaction_Interface`.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Btc_Rpc_Explorer_Api_Transaction_Adapter implements Transaction_Interface {

	public function __construct(
		protected TXSummary $transaction,
		protected int $block_height,
	) {
	}

	public function get_txid(): string {
		return $this->transaction->txId;
	}

	public function get_version(): int {
		return $this->transaction->version;
	}

	public function get_block_time(): DateTimeInterface {
		return $this->transaction->getBlockTime();
	}

	/**
	 * @see \BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\VIn
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array {
		// TODO: Implement get_v_in() method.
		// $this->transaction->vIn
		return array();
	}

	/**
	 * @see \BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\VOut
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array {
		return array_map(
			fn( VOut $v_out ) => new Transaction_VOut(
				value: Money::of( $v_out->value / 100000000, 'BTC' ),
				scriptpubkey_address: $v_out->scriptPubKey->address,
			),
			$this->transaction->vOut
		);
	}

	public function get_block_height(): ?int {
		return $this->block_height;
	}
}
