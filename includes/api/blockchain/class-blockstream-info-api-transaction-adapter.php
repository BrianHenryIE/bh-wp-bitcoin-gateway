<?php

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Blockchain;

use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_Interface;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VIn;
use BrianHenryIE\WP_Bitcoin_Gateway\API\Model\Transaction_VOut;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Exception\UnknownCurrencyException;
use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * @phpstan-import-type BlockStreamApiTransactionArray from Blockstream_Info_API
 * @phpstan-import-type BlockStreamApiTransactionVInArray from Blockstream_Info_API
 * @phpstan-import-type BlockStreamApiTransactionVOutArray from Blockstream_Info_API
 */
class BlockStream_Info_API_Transaction_Adapter implements Transaction_Interface {

	/**
	 * Constructor
	 *
	 * @param array&BlockStreamApiTransactionArray $blockstream_transaction The transaction data as returned by Blockstream.info API.
	 */
	public function __construct(
		protected array $blockstream_transaction
	) {
	}

	public function get_txid(): string {
		return (string) $this->blockstream_transaction['txid'];
	}

	/**
	 * @throws Exception
	 */
	public function get_block_time(): DateTimeInterface {

		$block_time = (int) $this->blockstream_transaction['status']['block_time'];

		return new DateTimeImmutable( '@' . $block_time, new DateTimeZone( 'UTC' ) );
	}


	public function get_block_height(): ?int {

		return $this->blockstream_transaction['status']['block_height'];

		// TODO: Confirmations was returning the block height - 1. Presumably that meant mempool/0 confirmations, but I need test data to understand.
		// Correct solution is probably to check does $blockstream_transaction['status']['block_height'] exist, else ???
		// Quick fix.
	}

	public function get_hash(): string {
		return $this->blockstream_transaction['status']['block_hash'];
	}

	/**
	 * @return int 1|2.
	 */
	public function get_version(): int {
		return $this->blockstream_transaction['version'];
	}

	/**
	 * @return Transaction_VIn[]
	 */
	public function get_v_in(): array {
		return array_map(
			array( $this, 'map_v_in' ),
			$this->blockstream_transaction['vin']
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
	 * @return Transaction_VOut[]
	 */
	public function get_v_out(): array {
		return array_map(
			array( $this, 'map_v_out' ),
			$this->blockstream_transaction['vout']
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
