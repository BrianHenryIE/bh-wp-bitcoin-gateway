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

	/**
	 * Polling schedule: first check after `poll_interval_ms`, then the gap doubles after every check up to
	 * `poll_max_interval_ms`, and nothing more is scheduled once `poll_duration_ms` has passed since page load.
	 * Exposed on `window` so tests can observe it.
	 */
	const polling = {
		polls: 0,
		schedules: 0,
		next_delay_ms: null as number | null,
		stopped_reason: null as string | null,
	};
	window.bh_wp_bitcoin_gateway_polling = polling;

	let pollTimer: ReturnType< typeof setTimeout > | undefined;
	let pollStartedAt = 0;
	let currentDelayMs = 0;

	function pollConfig(): {
		initial: number;
		max: number;
		duration: number;
	} {
		const data = window.bh_wp_bitcoin_gateway_ajax_data;
		return {
			initial: data?.poll_interval_ms ?? 0,
			max: data?.poll_max_interval_ms ?? 0,
			duration: data?.poll_duration_ms ?? 0,
		};
	}

	function startPolling(): void {
		const { initial } = pollConfig();

		if ( initial <= 0 ) {
			return;
		}

		const current = window.bh_wp_bitcoin_gateway_order_details;
		if (
			isPaid( current?.payment_status_key ?? '' ) ||
			isTerminalOrderStatus( current?.order_status ?? '' )
		) {
			return;
		}

		pollStartedAt = Date.now();
		currentDelayMs = initial;
		scheduleNextPoll();
	}

	/**
	 * Schedule the next check, or stop (and say so on the page) if the next one would fall outside the polling
	 * window.
	 */
	function scheduleNextPoll(): void {
		const { max, duration } = pollConfig();
		const elapsedMs = Date.now() - pollStartedAt;

		if ( duration > 0 && elapsedMs + currentDelayMs > duration ) {
			stopPolling( 'duration' );
			return;
		}

		polling.next_delay_ms = currentDelayMs;
		polling.schedules++;
		pollTimer = setTimeout( function (): void {
			pollTimer = undefined;
			polling.polls++;
			checkNow( true );
		}, currentDelayMs );

		// Back off for the following check.
		currentDelayMs = Math.min(
			currentDelayMs * 2,
			max > 0 ? max : currentDelayMs * 2
		);
	}

	/**
	 * @param reason Why polling stopped. A note is shown on the page unless the order no longer needs checking.
	 */
	function stopPolling( reason: string ): void {
		if ( pollTimer ) {
			clearTimeout( pollTimer );
			pollTimer = undefined;
		}
		polling.next_delay_ms = null;
		polling.stopped_reason = reason;

		if ( 'paid' !== reason ) {
			$( '.bh_wp_bitcoin_gateway_polling_stopped' ).fadeIn( 'slow' );
		}
	}

	function isPaid( statusKey: string ): boolean {
		return statusKey === 'paid';
	}

	function isPaymentSeen( statusKey: string ): boolean {
		return statusKey === 'awaiting_confirmation' || statusKey === 'paid';
	}

	/**
	 * An order that has been cancelled, failed or refunded will never be paid, so stop asking.
	 */
	function isTerminalOrderStatus( orderStatus: string ): boolean {
		return [ 'cancelled', 'failed', 'refunded' ].includes( orderStatus );
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
	}

	/**
	 * Ask the server to check the blockchain for this order's payment and update the page.
	 *
	 * @param scheduled Whether this check came from the polling schedule (which then decides what happens next)
	 *                  or from the customer clicking "last checked".
	 */
	function checkNow( scheduled: boolean = false ): void {
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

		function restoreOpacity(): void {
			$( '.bh-wp-bitcoin-gateway-details' ).removeClass( 'blockUI' );
			$( '.bh_wp_bitcoin_gateway_updatable' ).animate( {
				opacity: 1.0,
			} );
		}

		$.post( ajaxUrl, data )
			.done( function ( response: AjaxResponse ): void {
				$( '.bh-wp-bitcoin-gateway-details' ).removeClass( 'blockUI' );

				if ( ! response || ! response.success || ! response.data ) {
					// The server logged the problem; leave the page as it was and stop hammering it.
					restoreOpacity();
					stopPolling( 'error' );
					return;
				}

				// Compare the existing values,
				// If they are the same, just reset opacity,
				// If they are different, display:none the slow fade in.

				const newData = response.data;
				const current = window.bh_wp_bitcoin_gateway_order_details;

				const changed =
					current.btc_amount_received !==
						newData.btc_amount_received ||
					current.amount_unconfirmed !== newData.amount_unconfirmed ||
					current.payment_status_key !== newData.payment_status_key ||
					current.order_status !== newData.order_status;

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

					applyStatusKey( newData.payment_status_key );
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

				if ( isPaid( newData.payment_status_key ) ) {
					stopPolling( 'paid' );
				} else if ( isTerminalOrderStatus( newData.order_status ) ) {
					stopPolling( 'order_' + newData.order_status );
				} else if ( scheduled ) {
					scheduleNextPoll();
				}
			} )
			.fail( function ( jqXHR: JQuery.jqXHR ): void {
				// Non-2xx (expired nonce, unauthorized, server error): jQuery does not call the success callback
				// for these, so handle them here. The server has logged anything it could; stop polling.
				console.warn(
					'Bitcoin payment check failed:',
					jqXHR.status,
					jqXHR.statusText
				);
				restoreOpacity();
				stopPolling( 'http_' + jqXHR.status );
			} );
	}
} )( jQuery );
