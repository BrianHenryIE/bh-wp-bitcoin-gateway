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

interface PaymentLastCheckedDisplayProps {
	lastCheckedTimeFormatted?: string;
	showLabel?: boolean;
	isPreview?: boolean;
}

export const PaymentLastCheckedDisplay: React.FC<
	PaymentLastCheckedDisplayProps
> = ( { lastCheckedTimeFormatted, showLabel = true, isPreview = false } ) => {
	const [ displayPaymentLastChecked, setPaymentLastChecked ] =
		useState< string >( '' );

	useEffect( () => {
		if ( isPreview ) {
			setPaymentLastChecked( '5 minutes ago' );
		}
		if ( lastCheckedTimeFormatted ) {
			setPaymentLastChecked( lastCheckedTimeFormatted );
		}
	}, [] );

	return (
		<div className="bh-wp-bitcoin-gateway-payment-last-checked-block">
			{ showLabel && (
				<span className="payment-last-checked-label">
					{ __( 'Payment last-checked:', 'bh-wp-bitcoin-gateway' ) }
				</span>
			) }
			<span className="payment-last-checked-value">
				{ displayPaymentLastChecked }
			</span>
		</div>
	);
};
