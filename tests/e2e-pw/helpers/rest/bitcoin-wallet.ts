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

export async function listBitcoinWallets(): Promise<{
	count: number;
	data: BitcoinWalletSummary[];
}> {
	const baseURL: string = config.use.baseURL!;
	const url = `${ baseURL }/wp-json/e2e-test-helper/v1/bitcoin_wallets`;
	const response: Response = await fetch( url );
	return ( await response.json() ) as {
		count: number;
		data: BitcoinWalletSummary[];
	};
}

export async function resetBitcoinData(): Promise<void> {
	console.log('resetBitcoinData()');

	const baseURL: string = config.use.baseURL!;
	const url = `${ baseURL }/wp-json/e2e-test-helper/v1/bitcoin_wallets`;
	const response = await fetch( url, {
		method: 'DELETE',
		headers: {
			'Content-Type': 'application/json',
		},
	} );

	console.log(response.body);
}



