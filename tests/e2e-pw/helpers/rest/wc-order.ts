/**
 * Internal dependencies
 */
import config from '../../../../playwright.config';

/**
 * The development plugin authenticates all non-Store REST requests as admin, so `wc/v3` is usable directly.
 */
export type WooCommerceOrder = {
	id: number;
	status: string;
	date_created: string;
	date_paid: string | null;
	payment_method: string;
	total: string;
	[ key: string ]: any;
};

export async function getOrder( orderId: number ): Promise< WooCommerceOrder > {
	const baseURL: string = config.use.baseURL!;
	const response = await fetch(
		`${ baseURL }/wp-json/wc/v3/orders/${ orderId }`
	);
	if ( ! response.ok ) {
		throw new Error(
			`Failed to get order ${ orderId }: ${
				response.status
			} ${ await response.text() }`
		);
	}
	return await response.json();
}

/**
 * `wc/v3` ignores `date_created` on update, so this uses the development plugin's endpoint.
 *
 * @param orderId
 * @param dateCreated ISO 8601 in the site's timezone, e.g. `2026-09-24T00:00:00`.
 */
export async function setOrderDateCreated(
	orderId: number,
	dateCreated: string
): Promise< { id: number; date_created: string; date_created_gmt: string } > {
	const baseURL: string = config.use.baseURL!;
	const response = await fetch(
		`${ baseURL }/wp-json/e2e-test-helper/v1/orders/${ orderId }/date_created`,
		{
			method: 'PUT',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { date_created: dateCreated } ),
		}
	);
	if ( ! response.ok ) {
		throw new Error(
			`Failed to set order ${ orderId } date: ${
				response.status
			} ${ await response.text() }`
		);
	}
	return await response.json();
}
