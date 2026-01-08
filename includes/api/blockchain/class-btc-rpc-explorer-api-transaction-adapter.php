<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\TXSummary;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\VOut;

/**
 * Adapter that accepts a `BtcRpcExplorer\Model\TXSummary` and provides `API\Model\Transaction_Interface`.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Btc_Rpc_Explorer_Api_Transaction_Adapter {

	public function adapt(
		TXSummary $transaction,
		int $block_height,
	): Transaction_Interface {
		return new Transaction(
			tx_id: $transaction->txId,
			block_time: $transaction->getBlockTime(),
			version: $transaction->version,
			v_in: array(), // TODO:
			v_out: $this->get_v_out( $transaction ),
			block_height: $block_height,
		);
	}

	/**
	 * @see \BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\VOut
	 * @return Transaction_VOut[]
	 */
	public function get_v_out( TXSummary $transaction ): array {
		return array_map(
			fn( VOut $v_out ) => new Transaction_VOut(
				value: Money::of( $v_out->value / 100000000, 'BTC' ),
				scriptpubkey_address: $v_out->scriptPubKey->address,
			),
			$transaction->vOut
		);
	}
}
