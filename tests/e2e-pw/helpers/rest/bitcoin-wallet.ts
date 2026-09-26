/**
 * Internal dependencies
 */
import config from '../../../../playwright.config';

export type BitcoinWalletSummary = {
	id: number;
	title: string;
	name: string;
	status: string;
};

export async function listBitcoinWallets(): Promise< {
	count: number;
	data: BitcoinWalletSummary[];
} > {
	const baseURL: string = config.use.baseURL!;
	const url = `${ baseURL }/wp-json/e2e-test-helper/v1/bitcoin_wallets`;
	const response: Response = await fetch( url );
	return ( await response.json() ) as {
		count: number;
		data: BitcoinWalletSummary[];
	};
}

export async function resetBitcoinData(): Promise< void > {
	console.log( 'resetBitcoinData()' );

	const baseURL: string = config.use.baseURL!;
	const url = `${ baseURL }/wp-json/e2e-test-helper/v1/bitcoin_wallets`;
	const response = await fetch( url, {
		method: 'DELETE',
		headers: {
			'Content-Type': 'application/json',
		},
	} );

	console.log( response.body );
}

export type SeededBitcoinAddress = {
	post_id: number;
	address: string;
	derivation_path_sequence_number: number;
};

/**
 * Create the wallet for `xpub` and derive its first `count` addresses, marked "unused" without a blockchain
 * check, so an order can be placed against a known (possibly already funded) address.
 *
 * @see development-plugin/rest/class-bitcoin.php
 */
export async function seedBitcoinWalletAddresses(
	xpub: string,
	count: number = 1
): Promise< { wallet_post_id: number; addresses: SeededBitcoinAddress[] } > {
	const baseURL: string = config.use.baseURL!;
	const url = `${ baseURL }/wp-json/e2e-test-helper/v1/bitcoin_wallets/seed`;
	const response = await fetch( url, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( { xpub, count } ),
	} );
	if ( ! response.ok ) {
		throw new Error(
			`Failed to seed Bitcoin wallet addresses: ${
				response.status
			} ${ await response.text() }`
		);
	}
	return await response.json();
}
