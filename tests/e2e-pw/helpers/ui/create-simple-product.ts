/**
 * External dependencies
 */
import { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { testConfig } from '../../config/test-config';

import { loginAsAdmin, logout } from './login';

export type TestProduct = { name: string; price: string };

export async function createSimpleProduct(
	page: Page,
	product: TestProduct = testConfig.products.simple
) {
	// Login as admin
	await loginAsAdmin( page );

	// Navigate to products page
	await page.goto( '/wp-admin/edit.php?post_type=product' );

	// Check if simple product already exists
	const existingProduct = await page
		.locator( `text="${ product.name }"` )
		.first();
	const productExists = ( await existingProduct.count() ) > 0;

	if ( ! productExists ) {
		// Add new product
		await page.click( '.page-title-action' );

		// Fill product details
		await page.fill( '#title', product.name );

		// Set regular price
		await page.fill( '#_regular_price', product.price );

		// Publish product
		await page.click( '#publish' );
		await page.waitForSelector( '.notice-success' );
	}

	// Logout
	await logout( page );
}
