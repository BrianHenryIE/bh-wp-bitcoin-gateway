<?php
/**
 * Search and delete functions for Action Scheduler.
 *
 * @package brianhenryie/bh-wp-bitcoin-gateway
 */

namespace BrianHenryIE\WP_Bitcoin_Gateway\Development_Plugin\Rest;

use ActionScheduler;
use ActionScheduler_Abstract_RecurringSchedule;
use ActionScheduler_Action;
use ActionScheduler_NullAction;
use ActionScheduler_Schedule;
use ActionScheduler_Store;
use DateTime;
use Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Add REST endpoints to search and delete.
 */
class Action_Scheduler {

	/**
	 * Add hooks to register the REST endpoints.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_action_scheduler_search' ) );
		add_action( 'rest_api_init', array( $this, 'register_action_scheduler_delete' ) );
	}

	/**
	 * Add a REST endpoint for searching Action Scheduler actions.
	 *
	 * GET /wp-json/e2e-test-helper/v1/action_scheduler/search?hook={$hook}
	 *
	 * @hooked rest_api_init
	 */
	public function register_action_scheduler_search(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/action_scheduler/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'action_scheduler_search' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Search for Action Scheduler schedule events.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array{per_page?:int,orderby?:string,order?:string}> $request -- phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function action_scheduler_search( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		if ( ! function_exists( 'as_supports' ) ) {
			return new WP_Error( '', 'Action scheduler is not loaded.', array( 'status' => 500 ) );
		}

		$search = $request->get_params();

		/**
		 * @see ActionScheduler_DBStore::get_query_actions_sql()
		 */
		$search['per_page'] = $search['per_page'] ?? 200;
		$search['orderby']  = $search['orderby'] ?? 'date';
		$search['order']    = $search['order'] ?? 'ASC';
		/** @var array<ActionScheduler_Action> $scheduled_actions */
		$scheduled_actions = as_get_scheduled_actions( $search );

		/** @var ActionScheduler_Store $store */
		$store = ActionScheduler::store();

		/**
		 * @see \ActionScheduler_ListTable::prepare_items()
		 */
		$action_scheduler_action_to_array = function ( ActionScheduler_Action $action, int $index ) use ( $store ) {
			$schedule   = $action->get_schedule();
			$recurrence = $schedule instanceof ActionScheduler_Abstract_RecurringSchedule
				? $schedule->get_recurrence()
				: null;

			return array(
				'id'             => $index,
				'hook'           => $action->get_hook(),
				'status'         => $store->get_status( (string) $index ),
				'args'           => $action->get_args(),
				'group'          => $action->get_group(),
				/**
				 * Might be nice to use @see ActionScheduler_ListTable::human_interval()
				 */
				'recurrence'     => $recurrence,
				'scheduled_date' => $action->get_schedule()->next(),
				// 'log'
				'schedule'       => $action->get_schedule(),
				'hook_priority'  => $action->get_priority(),
			);
		};

		/** @var array<array<string,int|ActionScheduler_Schedule|DateTime|string|array<string, mixed>>> $results */
		$results = array();

		foreach ( $scheduled_actions as $index => $result ) {
			$results[ $index ] = $action_scheduler_action_to_array( $result, $index );
		}

		return new WP_REST_Response(
			array(
				'message' => 'Action Scheduler search results for: ' . str_replace( array( "\r", "\n", "\t" ), '', print_r( $search, true ) ),
				'count'   => count( $results ),
				'data'    => $results,
			),
			200
		);
	}

	/**
	 * Add a REST endpoint for deleting Action Scheduler actions.
	 *
	 * DELETE /wp-json/e2e-test-helper/v1/action_scheduler/{$id}
	 *
	 * @hooked rest_api_init
	 */
	public function register_action_scheduler_delete(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/action_scheduler/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'action_scheduler_delete' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Delete an Action Scheduler scheduled task by id (int).
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array{id?:string}> $request -- phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function action_scheduler_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {

		if ( ! function_exists( 'as_supports' ) ) {
			return new WP_Error( '', 'Action scheduler is not loaded.', array( 'status' => 500 ) );
		}

		/** @var string $id */
		$id = $request->get_param( 'id' );

		if ( ! $id ) {
			return new WP_Error( 'rest_missing_param', 'Missing id parameter.', array( 'status' => 400 ) );
		}

		/** @var ActionScheduler_Store $store */
		$store = ActionScheduler::store();

		$claim_id = $store->get_claim_id( $id );

		/** @var ActionScheduler_Action|ActionScheduler_NullAction $as */
		$as = $store->fetch_action( $id );

		if ( $as instanceof ActionScheduler_NullAction ) {
			return new WP_Error( 'rest_invalid_param', 'Invalid id: ' . $id, array( 'status' => 400 ) );
		}

		try {
			$store->delete_action( $id );
		} catch ( Exception $exception ) {
			return new WP_Error( 'rest_error', 'Invalid id: ' . $id . ' – ' . $exception->getMessage(), array( 'status' => 500 ) );
		}
		$claim_id_after = $store->get_claim_id( $id );

		return new WP_REST_Response(
			array(
				'message' => 'Action Scheduler delete ' . $id,
				'result'  => $claim_id !== $claim_id_after ? 'deleted' : 'not found',
				'success' => ! $claim_id_after,
			),
			200
		);
	}
}
