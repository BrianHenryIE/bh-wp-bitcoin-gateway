/**
 * Set the customer billing and shipping details via REST so they are prefilled at checkout.
 *
 * presumably, a "reset session" function would just delete the cookie.
 */

/**
 * External dependencies
 */
import { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import config from '../../../../playwright.config';
import { testConfig } from '../../config/test-config';

interface BillingAddress {
	first_name: string;
	last_name: string;
	company?: string;
	address_1: string;
	address_2: string;
	city: string;
	state: string;
	postcode: string;
	country: string;
	email: string;
	phone: string;
}

interface ShippingAddress {
	first_name: string;
	last_name: string;
	company?: string;
	address_1: string;
	address_2: string;
	city: string;
	state: string;
	postcode: string;
	country: string;
}

export async function updateCartCustomer(
	page: Page,
	billingAddress?: BillingAddress,
	shippingAddress?: ShippingAddress
): Promise< void > {
	const baseURL: string = config.use.baseURL!;

	// Visit any WooCommerce page to get the nonce (doesn't need to be checkout)
	// The AJAX endpoint will initialize the session if needed
	const currentUrl = page.url();
	const isOnSite = currentUrl.startsWith( baseURL );

	if ( ! isOnSite ) {
		// Visit shop page if not already on the site
		await page.goto( `${ baseURL }/shop` );
		await page.waitForLoadState( 'networkidle' );
	}

	// Make the AJAX call from within the browser context to ensure session cookie is included
	const result = await page.evaluate(
		async ( { billing, shipping } ) => {
			const formData = new URLSearchParams();
			formData.append( 'action', 'e2e_set_customer_data' );
			// @ts-ignore
			formData.append( 'security', window.woocommerce_params.e2e_set_customer_data_nonce );

			if ( billing ) {
				formData.append( 'billing_first_name', billing.first_name );
				formData.append( 'billing_last_name', billing.last_name );
				formData.append( 'billing_company', billing.company || '' );
				formData.append( 'billing_email', billing.email );
				formData.append( 'billing_phone', billing.phone );
				formData.append( 'billing_country', billing.country );
				formData.append( 'billing_address_1', billing.address_1 );
				formData.append( 'billing_address_2', billing.address_2 );
				formData.append( 'billing_city', billing.city );
				formData.append( 'billing_state', billing.state );
				formData.append( 'billing_postcode', billing.postcode );
			}

			if ( shipping ) {
				formData.append( 'shipping_first_name', shipping.first_name );
				formData.append( 'shipping_last_name', shipping.last_name );
				formData.append( 'shipping_company', shipping.company || '' );
				formData.append( 'shipping_country', shipping.country );
				formData.append( 'shipping_address_1', shipping.address_1 );
				formData.append( 'shipping_address_2', shipping.address_2 );
				formData.append( 'shipping_city', shipping.city );
				formData.append( 'shipping_state', shipping.state );
				formData.append( 'shipping_postcode', shipping.postcode );
			}

			const response = await fetch( '/wp-admin/admin-ajax.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: formData.toString(),
			} );

			const responseBody = await response.json();

			return {
				ok: response.ok,
				status: response.status,
				body: responseBody,
			};
		},
		{
			billing: billingAddress,
			shipping: shippingAddress,
		}
	);

	console.log( 'Customer data set via AJAX:', result.body );

	if ( ! result.ok || ! result.body.success ) {
		throw new Error(
			`Failed to set customer data: ${ result.status } ${ JSON.stringify( result.body ) }`
		);
	}
}

export async function setDefaultBillingAddress( page: Page ): Promise< void > {
	const billing = testConfig.addresses.customer.billing;

	const billingAddress: BillingAddress = {
		first_name: billing.firstname,
		last_name: billing.lastname,
		company: billing.company,
		address_1: billing.addressfirstline,
		address_2: billing.addresssecondline,
		city: billing.city,
		state: billing.state,
		postcode: billing.postcode,
		country: billing.country,
		email: billing.email,
		phone: billing.phone,
	};

	await updateCartCustomer( page, billingAddress );
}

export async function setDefaultShippingAddress( page: Page ): Promise< void > {
	const billing = testConfig.addresses.customer.billing;

	// Using billing address as shipping since testConfig doesn't have separate shipping
	const shippingAddress: ShippingAddress = {
		first_name: billing.firstname,
		last_name: billing.lastname,
		company: billing.company,
		address_1: billing.addressfirstline,
		address_2: billing.addresssecondline,
		city: billing.city,
		state: billing.state,
		postcode: billing.postcode,
		country: billing.country,
	};

	await updateCartCustomer( page, undefined, shippingAddress );
}

export async function setDefaultCustomerAddresses( page: Page ): Promise< void > {
	console.log('setDefaultCustomerAddresses()');

	const billing = testConfig.addresses.customer.billing;

	const billingAddress: BillingAddress = {
		first_name: billing.firstname,
		last_name: billing.lastname,
		company: billing.company,
		address_1: billing.addressfirstline,
		address_2: billing.addresssecondline,
		city: billing.city,
		state: billing.state,
		postcode: billing.postcode,
		country: billing.country,
		email: billing.email,
		phone: billing.phone,
	};

	// Using billing address as shipping since testConfig doesn't have separate shipping
	const shippingAddress: ShippingAddress = {
		first_name: billing.firstname,
		last_name: billing.lastname,
		company: billing.company,
		address_1: billing.addressfirstline,
		address_2: billing.addresssecondline,
		city: billing.city,
		state: billing.state,
		postcode: billing.postcode,
		country: billing.country,
	};

	await updateCartCustomer( page, billingAddress, shippingAddress );
}
