<?php

namespace GroundhoggBookingCalendar\Api;

use Groundhogg\Api\V4\Base_Object_Api;
use Groundhogg\Contact;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\Google_Connection;
use WP_REST_Request;
use function Groundhogg\after_form_submit_handler;
use function Groundhogg\do_replacements;
use function Groundhogg\get_contactdata;
use function Groundhogg\split_name;
use function Groundhogg\Ymd;

class Google_Connection_Api extends Base_Object_Api {

	public function get_db_table_name() {
		return 'google_connections';
	}

	public function register_routes() {
		parent::register_routes();

		$route = $this->get_route();
		$key   = $this->get_primary_key();

		register_rest_route( self::NAME_SPACE, "/{$route}/(?P<{$key}>\d+)/sync", [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_calendars' ],
				'permission_callback' => [ $this, 'update_permissions_callback' ]
			],
		] );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return Google_Connection
	 */
	public function get_object_from_request( WP_REST_Request $request ) {
		return parent::get_object_from_request( $request );
	}

	protected function get_object_class() {
		return Google_Connection::class;
	}

	/**
	 * Sync the calendars for a given connection
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function sync( WP_REST_Request $request ) {

		$id = $request->get_param( $this->get_primary_key() );

		$connection = new Google_Connection( $id );

		$connection->sync_calendars();

		return self::SUCCESS_RESPONSE( [
			'item' => $connection
		] );
	}

	public function update_permissions_callback() {
		return current_user_can( 'edit_calendar_integrations' );
	}

}

