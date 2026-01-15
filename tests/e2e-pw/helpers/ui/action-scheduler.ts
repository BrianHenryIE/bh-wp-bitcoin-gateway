export async function runActionInRow(
	page: any,
	actionSchedulerTableRow: any
) {
	const hookColumn = actionSchedulerTableRow.locator(
		'td[data-colname="Hook"]'
	);
	await hookColumn.hover();

	const runLink = hookColumn.locator( '.run a' );
	if ( ( await runLink.count() ) > 0 ) {
		await runLink.click();
		await page.waitForLoadState( 'networkidle' );
	}
}

export async function getActionSchedulerTableRowForOrder(
	page: any,
	orderId: number
) {
	const actionSchedulerUrl =
		'/wp-admin/tools.php?page=action-scheduler&status=pending&s=bh_wp_bitcoin_gateway_check_unpaid_order';
	await page.goto( actionSchedulerUrl );

	const rowSelector = `td[data-colname="Arguments"]:has-text("'order_id' => ${ orderId }")`;
	const tableRow = page.locator( rowSelector ).locator( '..' ).first();

	return ( await tableRow.count() ) > 0 ? tableRow : null;
}

export async function runActionSchedulerScheduledEvent(
	page: any,
	hook: string
) {
	const actionSchedulerUrl =
		'/wp-admin/tools.php?page=action-scheduler&status=pending&s=' + hook;
	await page.goto(actionSchedulerUrl);

	const pendingJob = page.locator(
		'td[data-colname="Hook"]:has-text("' + hook + '")'
	);

	if ((await pendingJob.count()) > 0) {
		// Run the job
		await pendingJob.hover();
		const runButton = pendingJob.locator('.run a');
		if ((await runButton.count()) > 0) {
			await runButton.click();
			await page.waitForLoadState('networkidle');
		}
	}

	// const rowSelector = `td[data-colname="Arguments"]:has-text("'order_id' => ${ orderId }")`;
	// const tableRow = page.locator( rowSelector ).locator( '..' ).first();
}
