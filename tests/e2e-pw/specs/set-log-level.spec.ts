/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import config from '../../../playwright.config';
import { loginAsAdmin } from '../helpers/ui/login';

test.describe( 'Set log level', () => {
	test( 'should respect the log level that is saved on the gateway settings page', async ( {
		page,
	} ) => {
		// Login as admin
		await loginAsAdmin( page );

		// Navigate to Bitcoin gateway settings
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=bh_bitcoin'
		);

		// Set log level to notice
		await page.selectOption(
			'#woocommerce_bitcoin_gateway_log_level',
			'notice'
		);

		// Save changes
		await page.click( '.woocommerce-save-button' );
		// await page.waitForSelector( '.notice-success' );

		await page.waitForLoadState( 'networkidle' );

		const baseURL: string = config.use.baseURL!;
		// Navigate to logs page
		await page.goto(
			baseURL + '/wp-admin/admin.php?page=bh-wp-bitcoin-gateway-logs'
		);

		// Verify log level is set to Notice
		await expect(
			page.locator( 'text=Current log level: Notice' )
		).toBeVisible();
	} );
} );
