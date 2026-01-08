<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

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

class Blockchain_Info_Api_Transaction_Adapter {

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

	protected function map_v_out( TransactionOut $out ): Transaction_VOut {
		return new Transaction_VOut(
			value: Money::of( $out->getValue(), 'BTC' )->dividedBy( 100_000_000 ),
			scriptpubkey_address: $out->getAddr(),
		);
	}
}
