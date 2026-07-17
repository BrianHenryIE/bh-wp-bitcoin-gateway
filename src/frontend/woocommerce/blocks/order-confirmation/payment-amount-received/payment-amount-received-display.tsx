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

interface PaymentAmountReceivedDisplayProps {
	btcAmountReceivedFormatted?: string;
	showLabel?: boolean;
	isPreview?: boolean;
}

export const PaymentAmountReceivedDisplay: React.FC<
	PaymentAmountReceivedDisplayProps
> = ( { btcAmountReceivedFormatted, showLabel = true, isPreview = false } ) => {
	const [ displayPaymentAmountReceived, setPaymentAmountReceived ] =
		useState< string >( '' );

	useEffect( () => {
		if ( isPreview ) {
			setPaymentAmountReceived( 'BTC 1.234' );
		}
		if ( btcAmountReceivedFormatted ) {
			setPaymentAmountReceived( btcAmountReceivedFormatted );
		}
	}, [] );

	return (
		<div className="bh-wp-bitcoin-gateway-payment-amount-received-block">
			{ showLabel && (
				<span className="payment-amount-received-label">
					{ __(
						'Payment amount-received:',
						'bh-wp-bitcoin-gateway'
					) }
				</span>
			) }
			<span className="payment-amount-received-value">
				{ displayPaymentAmountReceived }
			</span>
		</div>
	);
};
