<?php

namespace GroundhoggBookingCalendar\Api;

use Groundhogg\Api\V4\Base_Object_Api;
use Groundhogg\Contact;
use Groundhogg\Contact_Query;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\Synced_Event_Query;
use WP_REST_Request;
use function Groundhogg\after_form_submit_handler;
use function Groundhogg\array_find;
use function Groundhogg\array_map_to_class;
use function Groundhogg\array_map_to_method;
use function Groundhogg\do_replacements;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_time;
use function Groundhogg\split_name;
use function Groundhogg\Ymd;
use function Groundhogg\Ymd_His;

class Appointments_Api extends Base_Object_Api {

	public function get_db_table_name() {
		return 'appointments';
	}

	public function register_routes() {
		parent::register_routes();

		$route = $this->get_route();
		$key   = $this->get_primary_key();

		register_rest_route( self::NAME_SPACE, "/{$route}/events", [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'read_events' ],
				'permission_callback' => [ $this, 'read_permissions_callback' ]
			],
		] );

		register_rest_route( self::NAME_SPACE, "/{$route}/(?P<uuid>[A-z0-9\-]+)", [
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'cancel' ],
				'permission_callback' => [ $this, 'public_permissions_callback' ]
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'reschedule' ],
				'permission_callback' => [ $this, 'public_permissions_callback' ]
			],
		] );
	}

	public function read_events( WP_REST_Request $request ) {

		$before    = strtotime( $request->get_param( 'before' ) );
		$after     = strtotime( $request->get_param( 'after' ) );
		$calendars = wp_parse_id_list( $request->get_param( 'calendars' ) );

		$filters = $request->get_param( 'filters' ) ?: [];

		$query = wp_parse_args( [
			'before'      => $before,
			'after'       => $after,
			'orderby'     => $this->get_primary_key(),
			'order'       => 'DESC',
			'calendar_id' => $calendars,
			'limit'       => 999,
		] );

		if ( ! empty( $filters ) ) {

			$contact_query = new Contact_Query( [
				'filters' => $filters,
				'select'  => 'ID'
			] );

			$sql = $contact_query->get_sql();

			$query['contact_id'] = $sql;
		}

		$total = $this->get_db_table()->count( $query );
		$items = $this->get_db_table()->query( $query );

		global $wpdb;
		$last_query = $wpdb->last_query;

		$items = array_map_to_class( $items, Appointment::class );

		$synced = wp_parse_id_list( $request->get_param( 'synced' ) ) ?: [];

		if ( ! empty( $synced ) ) {

			$query = new Synced_Event_Query( [
				'from'      => $after,
				'to'        => $before,
				'calendars' => $synced
			] );

			$events = $query->get_results();
			// Filter out g events that are local events
			$events = array_filter( $events, function ( $event ) use ( $items ) {
				return ! array_find( $items, function ( $appt ) use ( $event ) {
					return $appt->g_uuid === $event->id;
				} );
			} );

			$total += count( $events );
			$items = array_merge( $events, $items );
		}

		$items = array_map_to_method( $items, 'get_for_full_calendar' );

		return self::SUCCESS_RESPONSE( [
			'total_items' => $total,
			'items'       => $items,
			'query'       => $last_query
		] );
	}

	/**
	 * Cancel an appointment
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel( WP_REST_Request $request ) {
		$uuid = $request->get_param( 'uuid' );

		if ( ! wp_is_uuid( $uuid ) ) {
			return new \WP_Error( 'invalid_uuid', 'Not a valid uuid' );
		}

		$appointment = new Appointment( $uuid, 'uuid' );

		if ( ! $appointment->exists() ) {
			return self::ERROR_RESOURCE_NOT_FOUND();
		}

		$appointment->add_note( sprintf( "<p><b>%s</b></p>", __( 'Cancelled:' ) ) . wpautop( sanitize_textarea_field( $request->get_param( 'reason' ) ) ) );

		$appointment->cancel();

		return self::SUCCESS_RESPONSE();
	}

	/**
	 * Cancel an appointment
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reschedule( WP_REST_Request $request ) {
		$uuid = $request->get_param( 'uuid' );

		if ( ! wp_is_uuid( $uuid ) ) {
			return new \WP_Error( 'invalid_uuid', 'Not a valid uuid' );
		}

		$appointment = new Appointment( $uuid, 'uuid' );

		if ( ! $appointment->exists() ) {
			return self::ERROR_RESOURCE_NOT_FOUND();
		}

		$start = get_time( $request->get_param( 'start' ) );

		$appointment->reschedule( [
			'start_time' => $start,
			'end_time'   => $start + $appointment->get_calendar()->get_appointment_length( true )
		] );

		$appointment->add_note( sprintf( "<p><b>%s</b></p>", __( 'Rescheduled:' ) ) . wpautop( sanitize_textarea_field( $request->get_param( 'reason' ) ) ) );

		return self::SUCCESS_RESPONSE( [
			'message'     => wpautop( do_replacements( $appointment->get_calendar()->get_meta( 'message' ), $appointment->get_contact() ) ),
			'appointment' => [
				'uuid' => $appointment->uuid,
			],
			'links'       => [
				'google' => $appointment->get_add_to_google_link(),
				'ics'    => $appointment->get_ics_link()
			]
		] );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return Calendar
	 */
	public function get_object_from_request( WP_REST_Request $request ) {
		return parent::get_object_from_request( $request );
	}

	protected function get_object_class() {
		return Appointment::class;
	}

	public function public_permissions_callback( WP_REST_Request $request ) {
		return wp_verify_nonce( $request->get_header( 'x-wp-nonce' ), 'wp_rest' );
	}

	public function read_permissions_callback() {
		return current_user_can( 'view_appointments' );
	}

	public function update_permissions_callback() {
		return current_user_can( 'edit_appointments' );
	}

	public function create_permissions_callback() {
		return current_user_can( 'add_appointments' );
	}

	public function delete_permissions_callback() {
		return current_user_can( 'delete_appointments' );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @param                  $cap
	 *
	 * @return bool|\WP_Error
	 */
	public function single_cap_check( \WP_REST_Request $request, $cap ) {
		$appointment = $this->get_object_from_request( $request );

		if ( ! $appointment->exists() ) {
			return self::ERROR_404();
		}

		return current_user_can( $cap, $appointment );
	}

	/**
	 * protect delete endpoint
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool|\WP_Error
	 */
	public function update_single_permissions_callback( \WP_REST_Request $request ) {
		return $this->single_cap_check( $request, 'edit_appointment' );
	}

	/**
	 * protect delete endpoint
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool|\WP_Error
	 */
	public function read_single_permissions_callback( \WP_REST_Request $request ) {
		return $this->single_cap_check( $request, 'view_appointment' );
	}

	/**
	 * protect delete endpoint
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return bool|\WP_Error
	 */
	public function delete_single_permissions_callback( \WP_REST_Request $request ) {
		return $this->single_cap_check( $request, 'delete_appointment' );
	}

}

