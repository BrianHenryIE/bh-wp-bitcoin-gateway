/**
 * WordPress dependencies
 */
/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import React, { useEffect, useState } from 'react';

interface PaymentStatusDisplayProps {
	paymentStatus?: string;
	showLabel?: boolean;
	isPreview?: boolean;
}

export const PaymentStatusDisplay: React.FC< PaymentStatusDisplayProps > = ( {
	paymentStatus,
	showLabel = true,
	isPreview = false,
} ) => {
	const [ displayPaymentStatus, setPaymentStatus ] = useState< string >( '' );

	useEffect( () => {
		if ( isPreview ) {
			setPaymentStatus( 'Awaiting payment' );
		}
		if ( paymentStatus ) {
			setPaymentStatus( paymentStatus );
		}
	}, [] );

	return (
		<div className="bh-wp-bitcoin-gateway-payment-status-block">
			{ showLabel && (
				<span className="payment-status-label">
					{ __( 'Payment status:', 'bh-wp-bitcoin-gateway' ) }
				</span>
			) }
			<span className="payment-status-value">
				{ displayPaymentStatus }
			</span>
		</div>
	);
};
