/**
 * Place an order against an address that already holds a confirmed payment, then check for payment and
 * expect the order to move to "processing".
 *
 * The wallet zpub6n37h…w97K's first address (derivation 0/0), bc1qzs6ttahakr604009st6vzgkjzx670uwvnfldcn,
 * received 5,934 sats on 24 September 2026 (block 968417). At any BTC price above ~$50,600 a $3 order is
 * covered by it; if this test starts failing on the amount, that is the first thing to check.
 *
 * The development plugin's seed endpoint derives the address and marks it "unused" without a blockchain
 * check; otherwise the plugin would see the payment, mark the address "used", and never assign it.
 */

/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { testConfig } from '../config/test-config';
import {
	resetBitcoinData,
	seedBitcoinWalletAddresses,
} from '../helpers/rest/bitcoin-wallet';
import {
	switchToShortcodeTheme,
	verifyTheme,
} from '../helpers/rest/theme-switcher';
import { getOrder, setOrderDateCreated } from '../helpers/rest/wc-order';
import { useShortcodeCheckout } from '../helpers/ui/checkout';
import { configureBitcoinXpub } from '../helpers/ui/configure-bitcoin-xpub';
import { createSimpleProduct } from '../helpers/ui/create-simple-product';
import { placeBitcoinOrder } from '../helpers/ui/place-bitcoin-order';

const MASTER_PUBLIC_KEY =
	'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K';
const FIRST_ADDRESS = 'bc1qzs6ttahakr604009st6vzgkjzx670uwvnfldcn';
const ORDER_DATE = '2026-09-24T00:00:00';

test.describe( 'Payment confirmation', () => {
	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await configureBitcoinXpub( page );
		await createSimpleProduct( page, testConfig.products.cheap );

		await useShortcodeCheckout();
		await switchToShortcodeTheme();
		await verifyTheme( 'shortcode' );

		await page.close();
	} );

	test.afterAll( async () => {
		// This spec wipes the wallet and cancels the plugin's address-replenishment jobs, so the next spec's
		// checkout would have to derive and blockchain-check a fresh address synchronously. Leave two ready.
		await seedBitcoinWalletAddresses( MASTER_PUBLIC_KEY, 2 );
	} );

	test( 'should mark the order processing when the payment at its address is seen', async ( {
		page,
	} ) => {
		// Start from a clean wallet so derivation index 0 is the address handed to this order.
		await resetBitcoinData();
		const seeded = await seedBitcoinWalletAddresses( MASTER_PUBLIC_KEY, 1 );

		expect(
			seeded.addresses[ 0 ].address,
			'The plugin should derive the known first address for the wallet'
		).toBe( FIRST_ADDRESS );

		const orderId = await placeBitcoinOrder(
			page,
			testConfig.products.cheap
		);
		expect( orderId ).toBeGreaterThan( 0 );

		await expect(
			page.locator( '.bh_wp_bitcoin_gateway_address' )
		).toHaveText( FIRST_ADDRESS );

		// The payment landed on 24 September 2026; date the order the same day.
		await setOrderDateCreated( orderId, ORDER_DATE );

		const before = await getOrder( orderId );
		expect( before.status ).toBe( 'on-hold' );
		expect( before.date_created ).toContain( '2026-09-24' );

		// Ask the server to check the blockchain now (this is what the page also does on its poll interval).
		await page.reload();
		const [ response ] = await Promise.all( [
			page.waitForResponse( ( r ) =>
				r.url().includes( 'admin-ajax.php' )
			),
			page.locator( '.bh_wp_bitcoin_gateway_last_checked_time' ).click(),
		] );
		const refreshed = await response.json();
		expect( refreshed.success ).toBe( true );
		expect( refreshed.data.order_status_formatted ).toBe( 'Processing' );

		const after = await getOrder( orderId );
		expect(
			after.status,
			`Order ${ orderId } should be processing once the payment at ${ FIRST_ADDRESS } is seen`
		).toBe( 'processing' );
		expect( after.date_paid ).not.toBeNull();
	} );
} );
