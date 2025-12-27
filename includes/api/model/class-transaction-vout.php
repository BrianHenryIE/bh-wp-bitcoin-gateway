<?php
/**
 *
 * @see https://developer.bitcoin.org/reference/transactions.html
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

readonly class Transaction_VOut {

	public function __construct(
		public Money $value,
		public int $n,
		public Transaction_VOut_ScriptPubKey $scriptPubKey,
	) {
	}
}
