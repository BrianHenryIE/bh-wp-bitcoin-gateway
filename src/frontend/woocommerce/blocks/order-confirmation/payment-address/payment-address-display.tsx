/**
 * External dependencies
 */
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import React, { useEffect, useState } from 'react';

/**
 * WordPress dependencies
 */

interface PaymentAddressDisplayProps {
	paymentAddress?: string;
	showLabel?: boolean;
	isPreview?: boolean;
}

export const PaymentAddressDisplay: React.FC< PaymentAddressDisplayProps > = ( {
	paymentAddress,
	showLabel = true,
	isPreview = false,
} ) => {
	const [ displayPaymentAddress, setPaymentAddress ] =
		useState< string >( '' );

	useEffect( () => {
		if ( isPreview ) {
			setPaymentAddress( 'xpub1234' );
		}
		if ( paymentAddress ) {
			setPaymentAddress( paymentAddress );
		}
	}, [] );

	return (
		<div className="bh-wp-bitcoin-gateway-payment-address-block">
			{ showLabel && (
				<span className="payment-address-label">
					{ __( 'Payment address:', 'bh-wp-bitcoin-gateway' ) }
				</span>
			) }
			<span className="payment-address-value">
				{ displayPaymentAddress }
			</span>
		</div>
	);
};
