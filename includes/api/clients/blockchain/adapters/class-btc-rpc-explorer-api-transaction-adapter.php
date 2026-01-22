<?php
/**
 * Adapter for BTC RPC Explorer transaction objects.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Adapters;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Payments\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Services\Exchange_Rate_Service;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\TXSummary;
use BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\VOut;

/**
 * Adapter that accepts a `BtcRpcExplorer\Model\TXSummary` and provides `API\Model\Transaction_Interface`.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 */
class Btc_Rpc_Explorer_Api_Transaction_Adapter {

	/**
	 * Adapt a BTC RPC Explorer transaction to the internal transaction interface.
	 *
	 * @param TXSummary $transaction The BTC RPC Explorer transaction object.
	 * @param int       $block_height The block height for the transaction.
	 * @return Transaction_Interface The adapted transaction.
	 */
	public function adapt(
		TXSummary $transaction,
		int $block_height,
	): Transaction_Interface {
		return new Transaction(
			tx_id: $transaction->txId,
			block_time: $transaction->getBlockTime(),
			version: $transaction->version,
			v_in: array(), // TODO: Implement `v_in` mapper.
			v_out: $this->get_v_out( $transaction ),
			block_height: $block_height,
		);
	}

	/**
	 * @see \BrianHenryIE\WP_Bitcoin_Gateway\BtcRpcExplorer\Model\VOut
	 * @param TXSummary $transaction The transaction object from the API.
	 * @return Transaction_VOut[]
	 * @throws \BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException If BTC has not been added to brick/money.
	 */
	public function get_v_out( TXSummary $transaction ): array {
		return array_map(
			fn( VOut $v_out ) => new Transaction_VOut(
				value: Money::of( $v_out->value / Exchange_Rate_Service::SATOSHI_RATE, 'BTC' ),
				scriptpubkey_address: $v_out->scriptPubKey->address,
			),
			$transaction->vOut
		);
	}
}
