/**
 * Internal dependencies
 */
import config from '../../../../playwright.config';

export async function removeMasterPublicKeyFromWooCommerceBitcoinPaymentGateway(): Promise<void> {
    const gatewayId = 'bh_bitcoin';
    const baseURL: string = config.use.baseURL!;
    const url = `${ baseURL }/wp-json/wc/v3/payment_gateways/${ gatewayId }`;

    const response = await fetch( url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify( {
            settings: [
                { id: 'xpub', value: '' },
            ],
        } ),
    } );

    if ( ! response.ok ) {
        const errorText = await response.text();
        throw new Error(
            `Failed to remove xpub from WooCommerce gateway ${ gatewayId }: ${ response.status } ${ errorText }`
        );
    }
}




