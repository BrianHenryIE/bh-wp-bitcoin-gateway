<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;
use DateTimeZone;

/**
 * @phpstan-import-type BlockStreamApiTransactionArray from Blockstream_Info_API
 * @phpstan-import-type BlockStreamApiTransactionVInArray from Blockstream_Info_API
 * @phpstan-import-type BlockStreamApiTransactionVOutArray from Blockstream_Info_API
 */
class BlockStream_Info_API_Transaction_Adapter {

	// TODO: Confirmations was returning the block height - 1. Presumably that meant mempool/0 confirmations, but I need test data to understand.
	// Correct solution is probably to check does $blockstream_transaction['status']['block_height'] exist, else ???
	// Quick fix.

	/**
	 * @param array&BlockStreamApiTransactionArray $blockstream_transaction The transaction data as returned by Blockstream.info API.
	 */
	public function adapt( array $blockstream_transaction ): Transaction_Interface {
		return new Transaction(
			tx_id: (string) $blockstream_transaction['txid'], // $blockstream_transaction['status']['block_hash'] ?
			block_time: new DateTimeImmutable( '@' . $blockstream_transaction['status']['block_time'], new DateTimeZone( 'UTC' ) ),
			version: $blockstream_transaction['version'],
			v_in:  array_map( array( $this, 'map_v_in' ), $blockstream_transaction['vin'] ),
			v_out: array_map( array( $this, 'map_v_out' ), $blockstream_transaction['vout'] ),
			block_height: $blockstream_transaction['status']['block_height'],
		);
	}

	/**
	 * @param BlockStreamApiTransactionVInArray&array $v_in
	 * @throws UnknownCurrencyException
	 */
	protected function map_v_in( array $v_in ): Transaction_VIn {
		return new Transaction_VIn(
			sequence: $v_in['sequence'],
			scriptsig: $v_in['scriptsig'],
			address: $v_in['prevout']['scriptpubkey_address'],
			prevout_scriptpubkey: $v_in['prevout']['scriptpubkey'],
			value: Money::of( $v_in['prevout']['value'] / 100000000, 'BTC' ),
			prev_out_n: $v_in['vout'],
		);
	}

	/**
	 * @param BlockStreamApiTransactionVOutArray&array $v_out
	 * @throws UnknownCurrencyException
	 */
	protected function map_v_out( array $v_out ): Transaction_VOut {
		return new Transaction_VOut(
			value: Money::of( $v_out['value'] / 100000000, 'BTC' ),
			scriptpubkey_address: $v_out['scriptpubkey_address'],
		);
	}
}
