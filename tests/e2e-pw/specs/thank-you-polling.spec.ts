/**
 * The thank-you page polls the server for the payment: first after one minute, then doubling the gap up to ten
 * minutes, and stops after an hour with a note on the page. Driven here with Playwright's fake clock, so the
 * hour takes seconds.
 */

/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { switchToShortcodeTheme } from '../helpers/rest/theme-switcher';
import { useShortcodeCheckout } from '../helpers/ui/checkout';
import { configureBitcoinXpub } from '../helpers/ui/configure-bitcoin-xpub';
import { createSimpleProduct } from '../helpers/ui/create-simple-product';
import { placeBitcoinOrder } from '../helpers/ui/place-bitcoin-order';

const MINUTE = 60_000;

/**
 * Advance the fake clock to the next scheduled poll and wait for the server's reply.
 */
async function runToNextPoll( page: Page ): Promise< number > {
	const before = await page.evaluate( () => ( {
		delay: window.bh_wp_bitcoin_gateway_polling?.next_delay_ms ?? null,
		schedules: window.bh_wp_bitcoin_gateway_polling?.schedules ?? 0,
	} ) );
	if ( before.delay === null ) {
		throw new Error( 'No poll is scheduled' );
	}

	await Promise.all( [
		page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) ),
		page.clock.runFor( before.delay ),
	] );

	// The next poll is scheduled (or polling stopped) from the response callback; wait for that to happen.
	await page.waitForFunction(
		( schedulesBefore ) =>
			( window.bh_wp_bitcoin_gateway_polling?.schedules ?? 0 ) >
				schedulesBefore ||
			window.bh_wp_bitcoin_gateway_polling?.stopped_reason !== null,
		before.schedules
	);

	return before.delay;
}

test.describe( 'Thank-you page polling', () => {
	let orderUrl: string;

	test.beforeAll( async ( { browser } ) => {
		// Placing the first order on a cold environment can mean deriving and checking a fresh address synchronously.
		test.setTimeout( 180_000 );
		const page = await browser.newPage();
		await configureBitcoinXpub( page );
		await createSimpleProduct( page );
		await useShortcodeCheckout();
		await switchToShortcodeTheme();
		await placeBitcoinOrder( page );
		orderUrl = page.url();
		await page.close();
	} );

	test( 'backs off to ten minutes and stops after an hour with a note', async ( {
		page,
	} ) => {
		await page.clock.install();

		const ajaxRequests: number[] = [];
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'admin-ajax.php' ) ) {
				ajaxRequests.push( Date.now() );
			}
		} );

		await page.goto( orderUrl );
		await expect(
			page.locator( '.bh-wp-bitcoin-gateway-details' )
		).toBeVisible();
		await expect(
			page.locator( '.bh_wp_bitcoin_gateway_polling_stopped' )
		).toBeHidden();

		const delays: number[] = [];
		while ( true ) {
			const stopped: string | null = await page.evaluate(
				() =>
					window.bh_wp_bitcoin_gateway_polling?.stopped_reason ?? null
			);
			if ( stopped !== null ) {
				break;
			}
			delays.push( await runToNextPoll( page ) );
			if ( delays.length > 20 ) {
				throw new Error( 'Polling did not stop' );
			}
		}

		// 1, 2, 4, 8 minutes, then capped at 10; the ninth poll would land at 65 minutes, past the hour.
		expect( delays ).toEqual( [
			1 * MINUTE,
			2 * MINUTE,
			4 * MINUTE,
			8 * MINUTE,
			10 * MINUTE,
			10 * MINUTE,
			10 * MINUTE,
			10 * MINUTE,
		] );
		expect( delays.reduce( ( a, b ) => a + b, 0 ) ).toBe( 55 * MINUTE );
		expect( ajaxRequests.length ).toBe( 8 );

		const reason = await page.evaluate(
			() => window.bh_wp_bitcoin_gateway_polling?.stopped_reason
		);
		expect( reason ).toBe( 'duration' );

		// Let the fade-in finish, then the note should be showing and no further polls happen.
		await page.clock.runFor( 2_000 );
		await expect(
			page.locator( '.bh_wp_bitcoin_gateway_polling_stopped' )
		).toBeVisible();

		await page.clock.runFor( 30 * MINUTE );
		expect( ajaxRequests.length ).toBe( 8 );
	} );

	test( 'stops polling and shows the note when the server refuses the request', async ( {
		page,
	} ) => {
		await page.clock.install();
		await page.goto( orderUrl );
		await expect(
			page.locator( '.bh-wp-bitcoin-gateway-details' )
		).toBeVisible();

		// An expired nonce is the realistic case for a tab left open; the server answers 400, which jQuery's
		// success callback never sees.
		await page.evaluate( () => {
			window.bh_wp_bitcoin_gateway_ajax_data.nonce = 'expired';
		} );

		const [ response ] = await Promise.all( [
			page.waitForResponse( ( r ) =>
				r.url().includes( 'admin-ajax.php' )
			),
			page.clock.runFor( 60_000 ),
		] );
		expect( response.status() ).toBe( 400 );

		await page.waitForFunction(
			() => window.bh_wp_bitcoin_gateway_polling?.stopped_reason !== null
		);
		expect(
			await page.evaluate(
				() => window.bh_wp_bitcoin_gateway_polling?.stopped_reason
			)
		).toBe( 'http_400' );

		await page.clock.runFor( 2_000 );
		await expect(
			page.locator( '.bh_wp_bitcoin_gateway_polling_stopped' )
		).toBeVisible();

		// Nothing further is scheduled.
		expect(
			await page.evaluate(
				() => window.bh_wp_bitcoin_gateway_polling?.next_delay_ms
			)
		).toBeNull();
	} );
} );
