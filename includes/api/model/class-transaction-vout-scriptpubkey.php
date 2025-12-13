<?php
/**
 *
 * @see https://developer.bitcoin.org/reference/transactions.html
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\API\Model;

readonly class Transaction_VOut_ScriptPubKey {

	public function __construct(
		public string $asm, // ": "OP_DUP OP_HASH160 89abcdefabbaabbaabbaabbaabbaabbaabbaabba OP_EQUALVERIFY OP_CHECKSIG",
		public string $hex, // ": "76a91489abcdefabbaabbaabbaabbaabbaabbaabbaabba88ac",
		public int $reqSigs, // ": 1,
		public string $type, // ": "pubkeyhash",
		public array $addresses, // ": [
		// "1DYzJKHt8VzFYHRWxQv5FMjB8wmqvfmEu9"
		// ]
	) {
	}
}
