/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { loginAsAdmin } from '../../helpers/ui/login';

test.describe( 'WooCommerce Payments admin menu submenu', () => {
	test( 'should respect the log level that is saved on the gateway settings page', async ( {
		page,
	} ) => {
		// Login as admin
		await loginAsAdmin( page );

		await page.goto( '/wp-admin/' );

		// Find the "Payments" menu item that WooCommerce adds.
		const paymentsMenu = await page.locator( '#toplevel_page_admin-page-wc-settings-tab-checkout-from-PAYMENTS_MENU_ITEM' );

		// Hover mouse over "Payments" menu
		await paymentsMenu.hover();

		// Wait for the submenu to appear
		await paymentsMenu.locator('.wp-submenu').waitFor({ state: 'visible' });

		// Find the Bitcoin submenu item
		const bitcoinSubmenu = paymentsMenu.locator( 'text=Bitcoin' );

		// Assert it is visible
		await expect(bitcoinSubmenu).toBeVisible();

		// Navigate to Bitcoin gateway settings
		await bitcoinSubmenu.click();

		// Assert it loaded the settings page
		await expect( page.locator( 'text=Accept Bitcoin payments' ) ).toBeVisible();
	} );
} );
