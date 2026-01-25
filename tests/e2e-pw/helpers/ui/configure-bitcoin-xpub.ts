/**
 * External dependencies
 */
import { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { loginAsAdmin, logout } from './login';

export async function configureBitcoinXpub( page: Page ) {
	// Login as admin
	await loginAsAdmin( page );

	// Navigate to Bitcoin gateway settings
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=bh_bitcoin'
	);

	// This is the empty "wp_plugin_wallet" wallet
	const xpub =
		'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K';

	// Check if it already filled in to save time
	const existingXpub = await page
		.locator( '#woocommerce_bh_bitcoin_xpub' )
		.inputValue();

	if ( existingXpub !== xpub ) {
		await page.fill( '#woocommerce_bh_bitcoin_xpub', xpub );
		await page.click( '.woocommerce-save-button' );

		// I think the save-button won't be active when `existingXpub === xpub` but I'm not sure exactly why this flow isn't working.
		await page.waitForLoadState( 'networkidle' );
		// await page.waitForSelector( '.notice-success' );
	}

	// Logout
	await logout( page );
}
