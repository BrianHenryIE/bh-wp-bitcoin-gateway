<?php
/**
 * Adapter for Blockchain.info transaction objects.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Clients\Blockchain\Adapters;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\Transaction as BlockchainInfo_Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\TransactionInput;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\TransactionOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Adapter that converts Blockchain.info transaction objects to internal Transaction_Interface.
 */
class Blockchain_Info_Api_Transaction_Adapter {

	/**
	 * Adapt a Blockchain.info transaction to the internal transaction interface.
	 *
	 * @param BlockchainInfo_Transaction $transaction The Blockchain.info transaction object.
	 * @return Transaction_Interface The adapted transaction.
	 */
	public function adapt( BlockchainInfo_Transaction $transaction ): Transaction_Interface {
		return new Transaction(
			tx_id: $transaction->getHash(),
			block_time: new DateTimeImmutable( '@' . $transaction->getTime(), new DateTimeZone( 'UTC' ) ),
			version: $transaction->getVer(),
			v_in: array_map( array( $this, 'map_t_in' ), $transaction->getInputs() ),
			v_out: array_map( array( $this, 'map_v_out' ), $transaction->getOut() ),
			block_height: $transaction->getBlockHeight(),
		);
	}

	/**
	 * Map a Blockchain.info transaction vector input to internal Transaction_VIn.
	 *
	 * @param TransactionInput $transaction_input The transaction input from Blockchain.info.
	 * @return Transaction_VIn The mapped transaction input.
	 */
	protected function map_t_in( TransactionInput $transaction_input ): Transaction_VIn {
		return new Transaction_VIn(
			sequence: $transaction_input->getSequence(),
			scriptsig: $transaction_input->getScript(),
			address: $transaction_input->getPrevOut()->getAddr(),
			prevout_scriptpubkey: $transaction_input->getPrevOut()->getScript(),
			value: Money::of( $transaction_input->getPrevOut()->getValue() / 100000000, 'BTC' ),
			prev_out_n: $transaction_input->getPrevOut()->getN(),
		);
	}

	/**
	 * Map a Blockchain.info transaction vector output to internal Transaction_VOut.
	 *
	 * @param TransactionOut $out The transaction output from Blockchain.info.
	 * @return Transaction_VOut The mapped transaction output.
	 */
	protected function map_v_out( TransactionOut $out ): Transaction_VOut {
		return new Transaction_VOut(
			value: Money::of( $out->getValue(), 'BTC' )->dividedBy( 100_000_000 ),
			scriptpubkey_address: $out->getAddr(),
		);
	}
}
