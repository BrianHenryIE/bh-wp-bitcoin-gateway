/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	getBitcoinAddressCount,
	deleteBitcoinAddresses,
} from '../helpers/rest/bitcoin-address';
import { configureBitcoinXpub } from '../helpers/ui/configure-bitcoin-xpub';
import { createSimpleProduct } from '../helpers/ui/create-simple-product';
import { loginAsAdmin } from '../helpers/ui/login';
import { placeBitcoinOrder } from '../helpers/ui/place-bitcoin-order';
import {fetchActions} from "../helpers/rest/action-scheduler";

test.describe( 'Generate new addresses', () => {
	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await configureBitcoinXpub( page );
		await createSimpleProduct( page );
		await page.close();
	} );

	test( 'should ensure new addresses after placing order', async ( {
		page,
	} ) => {

		const testStarted = new Date().toISOString();

		/**
		 * Delete all unused addresses
		 */
		const beforeDeletingUnusedCount = await getBitcoinAddressCount( 'unused' );
		console.log('beforeDeletingUnusedCount: ' + beforeDeletingUnusedCount);
		if ( beforeDeletingUnusedCount > 0 ) {
			await deleteBitcoinAddresses( beforeDeletingUnusedCount - 1, 'unused' );
		}

		const afterDeletingUnusedCount = await getBitcoinAddressCount( 'unused' );
		console.log('afterDeletingUnusedCount: ' + afterDeletingUnusedCount);


		// Place an order to trigger address generation
		await placeBitcoinOrder( page );

		const actionQueued = await fetchActions('bh_wp_bitcoin_gateway_single_ensure_unused_addresses', false, testStarted);
		expect( actionQueued.length ).toBe( 1 );

	} );

	// TODO: update to account for "trash"ed posts.
	test( 'should correctly report the all addresses count', async ( {
		page,
	} ) => {
		// Login as admin
		await loginAsAdmin( page );

		await page.goto( '/wp-admin/edit.php?post_type=bh-bitcoin-address' );

		// Get all address counts
		const allCountElement = page.locator( '.all a .count' );
		const allCountText = await allCountElement.textContent();
		const allCount = parseInt(
			allCountText?.replace( /\D/g, '' ) || '0'
		);

		expect( allCount ).not.toEqual( 0 );

		const unusedCountElement = page.locator( '.unused a .count' );
		const unusedCountText = await unusedCountElement.textContent();
		const unusedCount = parseInt(
			unusedCountText?.replace( /\D/g, '' ) || '0'
		);

		let assignedCount = 0;
		const assignedCountElement = page.locator( '.assigned a .count' );
		if ( ( await assignedCountElement.count() ) > 0 ) {
			const assignedCountText = await assignedCountElement.textContent();
			assignedCount = parseInt(
				assignedCountText?.replace( /\D/g, '' ) || '0'
			);
		}

		expect( unusedCount + assignedCount ).toEqual( allCount );
	} );
} );
