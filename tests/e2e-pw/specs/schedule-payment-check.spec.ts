/**
 * The plugin schedules a single shared `bh_wp_bitcoin_gateway_check_assigned_addresses_transactions`
 * Action Scheduler job (no per-order args) when an order is placed, and the job reschedules itself
 * while there are still assigned (unpaid) addresses.
 *
 * Earlier versions scheduled a per-order `bh_wp_bitcoin_gateway_check_unpaid_order` job with an
 * `order_id` argument and cancelled it when the order was paid; these tests were rewritten when
 * that design changed.
 */

/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	ActionSchedulerItem,
	fetchActions,
} from '../helpers/rest/action-scheduler';
import { switchToShortcodeTheme } from '../helpers/rest/theme-switcher';
import { runActionSchedulerScheduledEvent } from '../helpers/ui/action-scheduler';
import { configureBitcoinXpub } from '../helpers/ui/configure-bitcoin-xpub';
import { createSimpleProduct } from '../helpers/ui/create-simple-product';
import { loginAsAdmin } from '../helpers/ui/login';
import { placeBitcoinOrder } from '../helpers/ui/place-bitcoin-order';

const CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK =
	'bh_wp_bitcoin_gateway_check_assigned_addresses_transactions';

test.describe( 'Schedule payment checks', () => {
	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await configureBitcoinXpub( page );
		await createSimpleProduct( page );
		await page.close();
	} );

	async function fetchPendingCheckAssignedAddressesActions(): Promise<
		Record< string, ActionSchedulerItem >[]
	> {
		// `future = false` so a past-due pending action (scheduled but not yet run) also counts.
		const actions = await fetchActions(
			CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK,
			false
		);

		return actions.filter( ( action ) => action[ 1 ].status === 'pending' );
	}

	test( 'should schedule a payment check when a Bitcoin order is placed', async ( {
		page,
	} ) => {
		await switchToShortcodeTheme();

		const orderId = await placeBitcoinOrder( page );
		expect( orderId ).toBeGreaterThan( 0 );

		const pendingActions =
			await fetchPendingCheckAssignedAddressesActions();
		expect(
			pendingActions.length,
			`Expected a pending ${ CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK } Action Scheduler job after placing order ${ orderId }`
		).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'should not schedule a duplicate payment check when a second Bitcoin order is placed', async ( {
		page,
	} ) => {
		await placeBitcoinOrder( page );
		await placeBitcoinOrder( page );

		const pendingActions =
			await fetchPendingCheckAssignedAddressesActions();
		expect(
			pendingActions.length,
			`Expected exactly one pending ${ CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK } Action Scheduler job after placing two orders`
		).toBe( 1 );
	} );

	test( 'should schedule new payment check after each check that does not have payment', async ( {
		page,
	} ) => {
		const orderId = await placeBitcoinOrder( page );
		expect( orderId ).toBeGreaterThan( 0 );

		// Login as admin to run the pending action from the Action Scheduler admin UI.
		await loginAsAdmin( page );

		await runActionSchedulerScheduledEvent(
			page,
			CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK
		);

		// The order is unpaid, so its address is still assigned, so the job should have rescheduled itself.
		const pendingActions =
			await fetchPendingCheckAssignedAddressesActions();
		expect(
			pendingActions.length,
			`Expected ${ CHECK_ASSIGNED_ADDRESSES_TRANSACTIONS_HOOK } Action Scheduler job to reschedule itself while order ${ orderId } is unpaid`
		).toBeGreaterThanOrEqual( 1 );
	} );

	/**
	 * Earlier versions cancelled the per-order scheduled check when the order was marked paid.
	 * The current shared job stops rescheduling itself only when no addresses are assigned — a
	 * manual (admin) status change does not unassign the address, so checks continue until the
	 * blockchain confirms payment. If cancel-on-paid behavior is reintroduced, assert it here.
	 */
	test.fixme(
		'should cancel the scheduled check when the order is marked paid',
		async () => {}
	);
} );
