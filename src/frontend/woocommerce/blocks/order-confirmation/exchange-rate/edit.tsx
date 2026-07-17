/**
 * WordPress dependencies
 */
/**
 * External dependencies
 */

import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';

/**
 * Internal dependencies
 */
import { ExchangeRateDisplay } from './exchange-rate-display';

interface EditProps {
	attributes: {
		exchangeRate: string;
		showLabel: boolean;
		useUrl: boolean;
		exchangeRateUrl?: string;
	};
	setAttributes: (
		attributes: Partial< { showLabel: boolean; useUrl: boolean } >
	) => void;
	context: {
		'bh-wp-bitcoin-gateway/exchangeRate': string;
		'bh-wp-bitcoin-gateway/exchangeRateUrl': string;
	};
}

export const Edit: React.FC< EditProps > = ( {
	attributes,
	setAttributes,
	context,
} ) => {
	const { showLabel, useUrl } = attributes;

	const _contextExchangeRate: string | undefined =
		context[ 'bh-wp-bitcoin-gateway/exchangeRate' ];
	const _contextExchangeRateUrl: string | undefined =
		context[ 'bh-wp-bitcoin-gateway/exchangeRateUrl' ];

	const blockProps = useBlockProps( {
		className: 'bh-wp-bitcoin-gateway-exchange-rate',
	} );

	const exchangeRate = '$123,456';
	const exchangeRateUrl = 'https://blockchain.com';

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Exchange Rate Settings',
						'bh-wp-bitcoin-gateway'
					) }
				>
					<ToggleControl
						label={ __( 'Show label', 'bh-wp-bitcoin-gateway' ) }
						checked={ showLabel }
						onChange={ ( value: boolean ) =>
							setAttributes( { showLabel: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Link exchange rate to blockchain.com',
							'bh-wp-bitcoin-gateway'
						) }
						checked={ useUrl }
						onChange={ ( value: boolean ) =>
							setAttributes( { useUrl: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<ExchangeRateDisplay
					exchangeRate={ exchangeRate }
					showLabel={ showLabel }
					useUrl={ useUrl }
					exchangeRateUrl={ exchangeRateUrl }
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
						'Preview - actual rate will display on order confirmation pages',
						'bh-wp-bitcoin-gateway'
					) }
				</p>
			</div>
		</>
	);
};
