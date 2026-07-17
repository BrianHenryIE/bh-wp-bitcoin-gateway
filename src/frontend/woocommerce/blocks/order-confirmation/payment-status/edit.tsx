/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import { PaymentStatusDisplay } from './payment-status-display';

interface EditProps {
	attributes: {
		paymentStatus: string;
		showLabel: boolean;
	};
	setAttributes: (
		attributes: Partial< { paymentStatus: string; showLabel: boolean } >
	) => void;
	context: {
		'bh-wp-bitcoin-gateway/paymentStatus'?: string;
	};
}

export const Edit: React.FC< EditProps > = ( {
	attributes,
	setAttributes,
} ) => {
	const { showLabel, paymentStatus } = attributes;

	const blockProps = useBlockProps( {
		className: 'bh-wp-bitcoin-gateway-payment-status-block',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Payment Status Settings',
						'bh-wp-bitcoin-gateway'
					) }
				>
					<ToggleControl
						label={ __( 'Show label', 'bh-wp-bitcoin-gateway' ) }
						checked={ showLabel }
						onChange={ ( value ) =>
							setAttributes( { showLabel: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<PaymentStatusDisplay
					paymentStatus={ paymentStatus }
					showLabel={ showLabel }
					isPreview={ true }
				/>
				<p
					className="description"
					style={ {
						fontSize: '12px',
						opacity: 0.7,
						marginTop: '4px',
					} }
				>
					{ __(
						'Preview - actual status will display on order confirmation pages',
						'bh-wp-bitcoin-gateway'
					) }
				</p>
			</div>
		</>
	);
};
