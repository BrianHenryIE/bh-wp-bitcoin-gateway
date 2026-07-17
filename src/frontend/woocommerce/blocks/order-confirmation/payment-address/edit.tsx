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
import { PaymentAddressDisplay } from './payment-address-display';

interface EditProps {
	attributes: {
		showLabel: boolean;
	};
	setAttributes: ( attributes: Partial< { showLabel: boolean } > ) => void;
	context: {
		'bh-wp-bitcoin-gateway/paymentAddress': string;
	};
}

export const Edit: React.FC< EditProps > = ( {
	attributes,
	setAttributes,
} ) => {
	const { showLabel } = attributes;

	const blockProps = useBlockProps( {
		className: 'bh-wp-bitcoin-gateway-payment-address-block',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Payment Address settings',
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
				<PaymentAddressDisplay
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
						'Preview - actual address will display on order confirmation pages',
						'bh-wp-bitcoin-gateway'
					) }
				</p>
			</div>
		</>
	);
};
