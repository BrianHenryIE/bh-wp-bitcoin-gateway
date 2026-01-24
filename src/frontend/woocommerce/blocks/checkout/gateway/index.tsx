/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * WordPress dependencies
 */
import { getSetting } from '@woocommerce/settings';
import React from 'react';

import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

// TODO: Is there a more correct way to import this than via Composer/PHP?
/**
 * Internal dependencies
 */
import PaymentMethodConfig from '../../../../vendor/woocommerce/woocommerce/plugins/woocommerce/client/blocks/assets/js/blocks-registry/payment-methods/payment-method-config';

/**
 * @see Bitcoin_Gateway_Blocks_Checkout_Support::get_payment_method_data()
 */
interface BitcoinGatewaySettings {
	title?: string;
	description?: string;
	supports?: string[];
	currency_symbol?: string;
	exchange_rate_information?: string;
	bitcoin_image_src?: string;
}

const bhSettings: BitcoinGatewaySettings = getSetting(
	'bitcoin_gateway_data',
	{}
);

const bhDefaultLabel: string = __( 'Bitcoin', 'bh-wp-bitcoin-gateway' );

const bhLabel: string =
	decodeEntities( bhSettings.title || '' ) || bhDefaultLabel;

/**
 * Content component
 */
const BHContent: React.FC = (): React.ReactElement => {
	return (
		<div>
			<p className="wc-block-components-checkout-step__description">
				{ decodeEntities( bhSettings.description || '' ) }
			</p>
			<p className="wc-block-components-checkout-step__description">
				{ bhSettings.exchange_rate_information || '' }
			</p>
		</div>
	);
};

/**
 * Label component
 *
 * @param props Props from payment API.
 */
const BHLabel: React.FC< PaymentMethodProps > = (
	props
): React.ReactElement => {
	return (
		<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
			{ bhSettings.bitcoin_image_src && (
				<img
					src={ bhSettings.bitcoin_image_src }
					alt={ bhLabel }
					style={ { height: '24px' } }
				/>
			) }
		</div>
	);
};

/**
 * Payment method config object.
 */
const BitcoinGateway: PaymentMethodConfig = {
	name: 'bh_bitcoin',
	label: <BHLabel />,
	content: <BHContent />,
	edit: <BHContent />,
	canMakePayment: (): boolean => true,
	ariaLabel: bhLabel,
	supports: {
		features: bhSettings.supports || [],
	},
};

registerPaymentMethod( BitcoinGateway );
