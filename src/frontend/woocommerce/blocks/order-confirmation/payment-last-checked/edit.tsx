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
import { PaymentLastCheckedDisplay } from './payment-last-checked-display';

interface EditProps {
	attributes: {
		showLabel: boolean;
	};
	setAttributes: ( attributes: Partial< { showLabel: boolean } > ) => void;
	context: {
		'bh-wp-bitcoin-gateway/lastCheckedTimeFormatted': string;
	};
}

export const Edit: React.FC< EditProps > = ( {
	attributes,
	setAttributes,
} ) => {
	const { showLabel } = attributes;

	const blockProps = useBlockProps( {
		className: 'bh-wp-bitcoin-gateway-payment-last-checked-block',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Payment Addres settings',
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
				<PaymentLastCheckedDisplay
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
						'Preview - actual last-checked time will display on order confirmation pages',
						'bh-wp-bitcoin-gateway'
					) }
				</p>
			</div>
		</>
	);
};
