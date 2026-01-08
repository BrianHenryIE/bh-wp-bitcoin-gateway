<?php
/**
 * Vector-in/Value-in
 *
 * @see https://developer.bitcoin.org/reference/transactions.html
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

use BrianHenryIE\WP_Bitcoin_Gateway\Brick\Money\Money;

readonly class Transaction_VIn {
	public function __construct(
		public int $sequence,
		public string $scriptsig,
		// TODO: for transactions we are concerned about, this will usually be out payment address. Document when it might not be.
		public string $address,
		public string $prevout_scriptpubkey,
		public Money $value,
		public int $prev_out_n,
		// TODO: witness.
	) {
	}
}
