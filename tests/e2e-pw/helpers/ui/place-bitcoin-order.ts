/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { testConfig } from '../../config/test-config';

import { selectPaymentGateway } from './checkout';
import { logout } from './login';
import { setDefaultCustomerAddresses } from "../rest/wc-cart";

async function selectBitcoinPaymentMethod( page: Page ) {
	await selectPaymentGateway( page, 'bitcoin_gateway' );

	// Verify Bitcoin payment method description appears
	await expect(
		page.getByText( 'Pay quickly and easily with Bitcoin' )
	).toBeVisible();
}

export async function placeBitcoinOrder( page: Page ): Promise< number > {
	await logout( page );

	// Set the billing+shipping details via API.
	await setDefaultCustomerAddresses(page);

	// Go to shop
	await page.goto( '/shop/' );

	// Add simple product to cart
	await page.click( `text="${ testConfig.products.simple.name }"` );
	await page.click( '.single_add_to_cart_button' );

	// Go to checkout
	await page.goto( '/checkout/' );

	await selectBitcoinPaymentMethod( page );

	// Place order
	await page.getByText( 'Place Order' ).click();

	// Wait for order received page
	await page.waitForSelector( 'text=Order received' );

	// Extract order ID from URL
	const url = page.url();
	const orderIdMatch = url.match( /order-received\/(\d+)\// )!;
	return parseInt( orderIdMatch[ 1 ] );
}
