/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	listBitcoinWallets,
	resetBitcoinData,
} from '../helpers/rest/bitcoin-wallet';
import { removeMasterPublicKeyFromWooCommerceBitcoinPaymentGateway } from '../helpers/rest/wc-payment-gateway';
import { loginAsAdmin, logout } from '../helpers/ui/login';

test.describe( 'Wallet creation on settings save', () => {
	test( 'creates a bh-bitcoin-wallet post when saving master public key', async ( {
		page,
	} ) => {
		// Ensure a clean slate
		await removeMasterPublicKeyFromWooCommerceBitcoinPaymentGateway();
		await resetBitcoinData();

		// TODO: delete `woocommerce_bh_bitcoin_settings` wp_option to fully reset, add separate test for existing settings.

		// Confirm there are no wallets via REST API.
		// const restResponse = await page.request.get(
		// 	'/wp-json/wp/v2/bh-bitcoin-wallet'
		// );
		// expect( restResponse.ok() ).toBeTruthy();
		// const restData = await restResponse.json();
		// expect( Array.isArray( restData ) ? restData.length : 0 ).toBe( 0 );

		// Login and set xpub via UI
		await loginAsAdmin( page );

		// Confirm there are no wallets via UI.
		// await page.goto(
		// 	'/wp-admin/edit.php?post_status=active&post_type=bh-bitcoin-wallet'
		// );
		// await page.waitForSelector( 'text=Active (0)' );

		// await listBitcoinWallets();

		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=bh_bitcoin'
		);

		const xpub =
			'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K';

		// Wait for the field before filling. (Field id is `woocommerce_{gateway_id}_{field_key}`.)
		await page.locator( '#woocommerce_bh_bitcoin_xpub' ).waitFor();

		// Always hit save. If this is used as a utility function for other tests' arrange, then do it conditionally on change.
		await page.fill( '#woocommerce_bh_bitcoin_xpub', xpub );
		await page.click( '.woocommerce-save-button' );
		await page.waitForLoadState( 'networkidle' );

		// Fetch wallets via helper endpoint
		const wallets = await listBitcoinWallets();
		expect( wallets.count ).toBeGreaterThanOrEqual( 1 );
		expect(
			wallets.data.some(
				( w ) => w.title === xpub || w.name.includes( 'zpub' )
			)
		).toBeTruthy();

		await logout( page );
	} );
} );
