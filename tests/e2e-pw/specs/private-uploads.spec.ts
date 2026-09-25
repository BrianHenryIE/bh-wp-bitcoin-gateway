/**
 * The plugin's logs are written to `wp-content/uploads/logs`, which bh-wp-private-uploads guards with a rewrite
 * rule that routes requests through WordPress and only serves the file to users with `manage_options`.
 *
 * A probe file with a random secret is written there; nobody but an administrator should be able to read it.
 */

/**
 * External dependencies
 */
import { test, expect, request } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	createPrivateUploadsProbe,
	deletePrivateUploadsProbe,
	PrivateUploadsProbe,
} from '../helpers/rest/private-uploads';
import { loginAsAdmin, logout } from '../helpers/ui/login';

test.describe( 'Private uploads keep the logs directory private', () => {
	let probe: PrivateUploadsProbe;

	test.beforeAll( async () => {
		probe = await createPrivateUploadsProbe();
	} );

	test.afterAll( async () => {
		await deletePrivateUploadsProbe();
	} );

	test( 'redirects logged-out visitors to the login page', async ( {
		baseURL,
	} ) => {
		const anonymous = await request.newContext( {
			baseURL,
			maxRedirects: 0,
		} );

		const response = await anonymous.get( probe.url );

		expect(
			response.status(),
			'Expected a redirect, not the file'
		).toBeGreaterThanOrEqual( 300 );
		expect( response.status() ).toBeLessThan( 400 );
		expect( response.headers().location ).toContain( 'wp-login.php' );
		expect( await response.text() ).not.toContain( probe.secret );

		await anonymous.dispose();
	} );

	test( 'does not list the directory', async ( { baseURL } ) => {
		const anonymous = await request.newContext( { baseURL } );

		const response = await anonymous.get( '/wp-content/uploads/logs/' );

		expect( response.status() ).not.toBe( 200 );
		expect( await response.text() ).not.toContain(
			'e2e-private-uploads-probe'
		);

		await anonymous.dispose();
	} );

	test( 'refuses a logged-in customer', async ( { page } ) => {
		await logout( page );
		await page.goto( '/?login_as_user=customer' );

		const response = await page.request.get( probe.url, {
			maxRedirects: 0,
		} );

		expect( response.status() ).toBe( 403 );
		expect( await response.text() ).not.toContain( probe.secret );
	} );

	test( 'serves the file to an administrator', async ( { page } ) => {
		await loginAsAdmin( page );

		const response = await page.request.get( probe.url );

		expect( response.status() ).toBe( 200 );
		expect( await response.text() ).toContain( probe.secret );
	} );
} );
