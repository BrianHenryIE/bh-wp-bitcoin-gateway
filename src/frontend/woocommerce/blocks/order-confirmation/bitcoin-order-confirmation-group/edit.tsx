/**
 * WordPress dependencies
 */
/**
 * External dependencies
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import React from 'react';

interface EditProps {
	attributes: {
		orderId: number;
	};
	setAttributes: ( attributes: Partial< { orderId: number } > ) => void;
	context: Record< string, any >;
}

export const Edit: React.FC< EditProps > = ( {} ) => {
	const blockProps = useBlockProps( {
		className: 'bh-wp-bitcoin-gateway-bitcoin-order-container',
	} );

	return (
		<div { ...blockProps }>
			<div className="wp-block-group">
				<div className="wp-block-group__inner-container">
					<InnerBlocks
						templateLock={ false }
						renderAppender={ InnerBlocks.DefaultBlockAppender }
						// defaultBlock={['core/paragraph', {placeholder: "Lorem ipsum..."}]}
						// directInsert
					/>
				</div>
			</div>
		</div>
	);
};
