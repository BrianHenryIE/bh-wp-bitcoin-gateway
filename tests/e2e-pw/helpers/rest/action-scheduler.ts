/**
 * Internal dependencies
 */
import { debugFetch } from '../fetch';

// GET /wp-json/e2e-test-helper/v1/action_scheduler/search?hook={$hook}

export interface ActionSchedulerItem {
	id: number;
	hook: string;
	status: string;
	args: {
		order_id?: number;
		[ key: string ]: any;
	};
	group: string;
	recurrence: number;
	scheduled_date: {
		date: string;
		timezone_type: number;
		timezone: string;
	};
	schedule: Record< string, any >;
	hook_priority: number;
}

export async function fetchActions(
	hook: string,
	future: boolean = true,
	since: string|null = null
): Promise< Record< string, ActionSchedulerItem >[] > {
	let path = `/wp-json/e2e-test-helper/v1/action_scheduler/search?hook=${ hook }`;
	// Does this include past-due?
	if ( since ) {
		path += `&date_compare=>=&date=${ since }`;
	} else if ( future ) {
		path += `&date_compare=>=&date=${ new Date().toISOString() }`;
	}
	const response = await debugFetch( path );
	const json = await response.json();

	return Object.entries( json.data ); //as [Record<string, ActionSchedulerItem>];
}

export async function fetchActionsWithArgs(
	hook: string,
	searchArgs?: object
): Promise< Record< string, ActionSchedulerItem >[] > {
	const results = await fetchActions( hook );

	if ( ! searchArgs ) {
		return results;
	}

	const filteredResults: Record< string, ActionSchedulerItem >[] = [];

	for ( const [ _key, action ] of Object.entries( results ) ) {
		let matches = true;
		for ( const [ k, v ] of Object.entries( searchArgs ) ) {
			if ( action[ 1 ].args[ k ] !== v ) {
				matches = false;
				break;
			}
		}
		if ( matches ) {
			filteredResults.push( action );
		}
	}

	return filteredResults;
}

export async function deleteAction( actionId: number ): Promise< void > {
	const path = `/wp-json/e2e-test-helper/v1/action_scheduler/${ actionId }`;
	const response = await debugFetch( path, {
		method: 'DELETE',
		headers: {
			'Content-Type': 'application/json',
		},
	} );

	const body = await response.text();
	console.log( body );
}
