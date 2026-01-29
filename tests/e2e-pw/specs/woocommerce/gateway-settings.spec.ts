/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { loginAsAdmin } from '../../helpers/ui/login';

test.describe( 'WooCommerce Bitcoin Gateway settings page', () => {
	test( 'should contain a working link to Action Scheduler', async ( {
		page,
	} ) => {
		// Login as admin
		await loginAsAdmin( page );

		// Navigate to Bitcoin gateway settings page
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=bh_bitcoin' );

		// Find the "View Scheduled Actions" link
		const actionSchedulerLink = page.getByRole( 'link', { name: 'View Scheduled Actions' } );

		// Assert the link is visible
		await expect( actionSchedulerLink ).toBeVisible();

		// Verify the link has the correct href with query parameters
		const href = await actionSchedulerLink.getAttribute( 'href' );
		expect( href ).toContain( 'tools.php' );
		expect( href ).toContain( 'page=action-scheduler' );
		expect( href ).toContain( 'status=pending' );
		expect( href ).toContain( 'orderby=schedule' );
		expect( href ).toContain( 'order=desc' );
		expect( href ).toContain( 's=bh_wp_bitcoin_gateway' );

		// Click the link and verify it navigates to Action Scheduler
		await actionSchedulerLink.click();

		// Wait for navigation to complete
		await page.waitForURL( '**/tools.php?**' );

		// Verify we're on the Action Scheduler page
		await expect( page.getByRole( 'heading', { name: 'Scheduled Actions', level: 1 } ) ).toBeVisible();

		// Verify the URL contains the search parameter
		expect( page.url() ).toContain( 's=bh_wp_bitcoin_gateway' );
	} );
} );
