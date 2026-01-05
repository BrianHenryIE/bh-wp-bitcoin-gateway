<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\TransactionInput;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\TransactionOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * As an adapter, this class takes an object of another class, implements an interface, and its methods parse the given
 * object to return values adhering to the interface. Distinct from a static method on a factory, this is "lazy".
 */
class Blockchain_Info_Api_Transaction_Adapter implements Transaction_Interface {

	public function __construct(
		protected Transaction $transaction
	) {
	}

	public function get_txid(): string {
		return $this->transaction->getHash();
	}

	/**
	 * @throws Exception In the unlikely event that the timestamp is invalid.
	 */
	public function get_block_time(): DateTimeInterface {
		return new DateTimeImmutable( '@' . $this->transaction->getTime(), new DateTimeZone( 'UTC' ) );
	}

	public function get_block_height(): ?int {
		return $this->transaction->getBlockHeight();
	}


	public function get_hash(): string {
		return $this->transaction->getHash();
	}

	public function get_version(): int {
		return $this->transaction->getVer();
	}

	/**
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array {
		return array_map(
			array( $this, 'map_t_in' ),
			$this->transaction->getInputs()
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

	/**
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array {
		return array_map(
			array( $this, 'map_v_out' ),
			$this->transaction->getOut()
		);
	}

	protected function map_v_out( TransactionOut $out ): Transaction_VOut {
		return new Transaction_VOut(
			value: Money::of( $out->getValue(), 'BTC' )->dividedBy( 100_000_000 ),
			scriptpubkey_address: $out->getAddr(),
		);
	}
}
