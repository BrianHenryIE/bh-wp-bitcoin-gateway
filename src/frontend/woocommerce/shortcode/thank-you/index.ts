/* global navigator, jQuery, bh_wp_bitcoin_gateway_order_details, bh_wp_bitcoin_gateway_ajax_data */

( function ( $: JQueryStatic ): void {
	'use strict';

	// the ajax response should have an optional redirect URL.
	// e.g. for a software purchase, redirect to the download page.

	$( function (): void {
		$( '.bh_wp_bitcoin_gateway_address' ).click(
			async function (): Promise< void > {
				const address: string =
					window.bh_wp_bitcoin_gateway_order_details?.btc_address;

				try {
					// Copy it to the clipboard.
					await navigator.clipboard.writeText( address );

					// Visual indication that the text has been copied.
					$( this ).css( 'display', 'none' );
					$( this ).fadeIn( 'slow' );
				} catch ( error ) {
					console.warn(
						'Failed to copy address to clipboard:',
						error
					);
				}
			}
		);

		$( '.bh_wp_bitcoin_gateway_total' ).click(
			async function (): Promise< void > {
				const amount: string =
					window.bh_wp_bitcoin_gateway_order_details?.btc_total;

				try {
					// Copy it to the clipboard.
					await navigator.clipboard.writeText( amount );

					// Visual indication that the text has been copied.
					$( this ).css( 'display', 'none' );
					$( this ).fadeIn( 'slow' );
				} catch ( error ) {
					console.warn( 'Failed to copy total to clipboard:', error );
				}
			}
		);

		$( '.bh_wp_bitcoin_gateway_last_checked_time' ).click(
			function (): void {
				checkNow();
			}
		);

		startPolling();
	} );

	let pollTimer: ReturnType< typeof setInterval > | undefined;

	/**
	 * Poll the server (which polls the blockchain and mempool) until the order is paid, so the customer sees
	 * "payment seen" without refreshing the page.
	 */
	function startPolling(): void {
		const intervalMs: number =
			window.bh_wp_bitcoin_gateway_ajax_data?.poll_interval_ms ?? 0;

		if ( intervalMs <= 0 || isPaid( getCurrentStatusKey() ) ) {
			return;
		}

		pollTimer = setInterval( checkNow, intervalMs );
	}

	function stopPolling(): void {
		if ( pollTimer ) {
			clearInterval( pollTimer );
			pollTimer = undefined;
		}
	}

	function getCurrentStatusKey(): string {
		return (
			window.bh_wp_bitcoin_gateway_order_details?.payment_status_key ?? ''
		);
	}

	function isPaid( statusKey: string ): boolean {
		return statusKey === 'paid';
	}

	function isPaymentSeen( statusKey: string ): boolean {
		return statusKey === 'awaiting_confirmation' || statusKey === 'paid';
	}

	/**
	 * Once the full payment has been seen there is nothing left to pay: hide the QR code and tell the customer.
	 */
	function applyStatusKey( statusKey: string ): void {
		if ( isPaymentSeen( statusKey ) ) {
			$( '.bh_wp_bitcoin_gateway_qr' ).fadeOut( 'slow' );
			$( '.bh_wp_bitcoin_gateway_payment_seen' ).fadeIn( 'slow' );
		} else {
			$( '.bh_wp_bitcoin_gateway_qr' ).show();
			$( '.bh_wp_bitcoin_gateway_payment_seen' ).hide();
		}

		if ( isPaid( statusKey ) ) {
			stopPolling();
		}
	}

	function checkNow(): void {
		const ajaxUrl: string = window.bh_wp_bitcoin_gateway_ajax_data.ajax_url;
		const nonce: string = window.bh_wp_bitcoin_gateway_ajax_data.nonce;
		const orderId: string =
			window.bh_wp_bitcoin_gateway_order_details.order_id;

		// Let's fade out the numbers to indicate they are maybe about to be updated.
		$( '.bh_wp_bitcoin_gateway_updatable' ).animate( { opacity: 0.4 } );

		$( '.bh-wp-bitcoin-gateway-details' ).addClass( 'blockUI' );

		const data = {
			action: 'bh_wp_bitcoin_gateway_refresh_order_details',
			_ajax_nonce: nonce,
			order_id: orderId,
		};

		$.post( ajaxUrl, data, function ( response: AjaxResponse ): void {
			$( '.bh-wp-bitcoin-gateway-details' ).removeClass( 'blockUI' );

			if ( ! response || ! response.success || ! response.data ) {
				// The server logged the problem; leave the page as it was and stop hammering it.
				$( '.bh_wp_bitcoin_gateway_updatable' ).animate( {
					opacity: 1.0,
				} );
				stopPolling();
				return;
			}

			// Compare the existing values,
			// If they are the same, just reset opacity,
			// If they are different, display:none the slow fade in.

			const newData = response.data;
			const current = window.bh_wp_bitcoin_gateway_order_details;

			const changed =
				current.btc_amount_received !== newData.btc_amount_received ||
				current.amount_unconfirmed !== newData.amount_unconfirmed ||
				current.payment_status_key !== newData.payment_status_key;

			if ( changed ) {
				// We have a new payment (or a new confirmation)!
				$( '.bh_wp_bitcoin_gateway_updatable' ).css(
					'display',
					'none'
				);

				$( '.bh_wp_bitcoin_gateway_status' ).text( newData.status );
				$( '.bh_wp_bitcoin_gateway_amount_received' ).text(
					newData.amount_received
				);
				$( '.bh_wp_bitcoin_gateway_amount_unconfirmed' ).text(
					newData.amount_unconfirmed
				);
				$( '.order-status' ).text( newData.order_status_formatted );

				// TODO: Transactions.

				$( '.bh_wp_bitcoin_gateway_updatable' ).fadeIn( 'slow' );
			} else {
				// Return to regular opacity
				$( '.bh_wp_bitcoin_gateway_updatable' ).animate( {
					opacity: 1.0,
				} );
			}

			$( '.bh_wp_bitcoin_gateway_last_checked_time' ).text(
				newData.last_checked_time_formatted
			);

			for ( const key of Object.keys( newData ) ) {
				window.bh_wp_bitcoin_gateway_order_details[ key ] =
					newData[ key ];
			}

			applyStatusKey( newData.payment_status_key );
		} );
	}
} )( jQuery );
