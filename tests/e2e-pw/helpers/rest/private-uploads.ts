/**
 * Internal dependencies
 */
import config from '../../../../playwright.config';

export type PrivateUploadsProbe = { url: string; path: string; secret: string };

/**
 * Write a probe file into the private `logs` uploads directory and return its URL and the secret it contains.
 *
 * @see development-plugin/rest/class-private-uploads.php
 */
export async function createPrivateUploadsProbe(): Promise< PrivateUploadsProbe > {
	const baseURL: string = config.use.baseURL!;
	const response = await fetch(
		`${ baseURL }/wp-json/e2e-test-helper/v1/private_uploads/probe`,
		{ method: 'POST' }
	);
	if ( ! response.ok ) {
		throw new Error(
			`Failed to create private uploads probe: ${
				response.status
			} ${ await response.text() }`
		);
	}
	return await response.json();
}

export async function deletePrivateUploadsProbe(): Promise< void > {
	const baseURL: string = config.use.baseURL!;
	await fetch(
		`${ baseURL }/wp-json/e2e-test-helper/v1/private_uploads/probe`,
		{ method: 'DELETE' }
	);
}
