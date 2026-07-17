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

interface PaymentTotalDisplayProps {
	btcTotalFormatted?: string;
	showLabel?: boolean;
	isPreview?: boolean;
}

export const PaymentTotalDisplay: React.FC< PaymentTotalDisplayProps > = ( {
	btcTotalFormatted,
	showLabel = true,
	isPreview = false,
} ) => {
	const [ displayPaymentTotal, setPaymentTotal ] = useState< string >( '' );

	useEffect( () => {
		if ( isPreview ) {
			setPaymentTotal( 'BTC 1.234' );
		}
		if ( btcTotalFormatted ) {
			setPaymentTotal( btcTotalFormatted );
		}
	}, [] );

	return (
		<div className="bh-wp-bitcoin-gateway-payment-total-block">
			{ showLabel && (
				<span className="payment-total-label">
					{ __( 'Payment total:', 'bh-wp-bitcoin-gateway' ) }
				</span>
			) }
			<span className="payment-total-value">{ displayPaymentTotal }</span>
		</div>
	);
};
