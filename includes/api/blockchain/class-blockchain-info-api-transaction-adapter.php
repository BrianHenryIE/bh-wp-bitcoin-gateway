<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut_ScriptPubKey;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\Transaction;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\TransactionInput;
use BrianHenryIE\WP_Bitcoin_Gateway\BlockchainInfo\Model\TransactionOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * As an adapter, this class takes an object of another class, implement an interface, and its methods parse the given
 * object to return values adhering to the interface.
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
		// TODO.
		return new Transaction_VIn();
	}

	/**
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array {

		return array_map(
			function ( TransactionOut $out ): Transaction_VOut {
					// TODO:
				$a            = $out->getSpendingOutpoints();
				$b            = array_pop( $a );
				$scriptPubKey = $out->getScript();
				return new Transaction_VOut(
					// type: int 0
					// spent bool true
					// TODO: choose correct divided by...
					value: Money::of( $out->getValue(), 'BTC' )->dividedBy( 100_000_000 ),
					n: $out->getN(),
					// TODO:
					// scriptPubKey: $out->getScript(),
					// scriptPubKey: $out->getSpendingOutpoints(),
					// a914c15f1ad5162b35d8ddb3cf46009326d36252237187
					scriptPubKey: new Transaction_VOut_ScriptPubKey(
						asm: 'OP_DUP OP_HASH160 89abcdefabbaabbaabbaabbaabbaabbaabbaabba OP_EQUALVERIFY OP_CHECKSIG',
						hex: '76a91489abcdefabbaabbaabbaabbaabbaabbaabbaabba88ac',
						reqSigs: 1,
						type: 'pubkeyhash',
						addresses: array(),
					),
				);
			},
			$this->transaction->getOut()
		);
	}
}
