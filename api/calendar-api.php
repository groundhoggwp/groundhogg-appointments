<?php

namespace GroundhoggBookingCalendar\Api;

use Groundhogg\Api\V4\Base_Object_Api;
use Groundhogg\Contact;
use GroundhoggBookingCalendar\Classes\Calendar;
use WP_REST_Request;
use function Groundhogg\after_form_submit_handler;
use function Groundhogg\do_replacements;
use function Groundhogg\get_contactdata;
use function Groundhogg\split_name;
use function Groundhogg\Ymd;

class Calendar_Api extends Base_Object_Api {

	public function get_db_table_name() {
		return 'calendars';
	}

	public function register_routes() {
		parent::register_routes();

		$route = $this->get_route();
		$key   = $this->get_primary_key();

		register_rest_route( self::NAME_SPACE, "/{$route}/(?P<{$key}>\d+)/availability", [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'read_availability' ],
				'permission_callback' => [ $this, 'public_permissions_callback' ]
			],
		] );

		register_rest_route( self::NAME_SPACE, "/{$route}/(?P<{$key}>\d+)/schedule", [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_new_appointment' ],
				'permission_callback' => [ $this, 'public_permissions_callback' ]
			],
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
		return Calendar::class;
	}

	/**
	 * Get the slots of a calendar for a time range
	 *
	 * @throws \Exception
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function read_availability( \WP_REST_Request $request ) {

		$start = $request->get_param( 'start' ) ?: Ymd();
		$end   = $request->get_param( 'end' );

		/**
		 * @var $calendar Calendar
		 */
		$calendar = $this->get_object_from_request( $request );

		if ( ! $calendar->exists() ) {
			return self::ERROR_404();
		}

		return self::SUCCESS_RESPONSE( [
			'slots' => $calendar->get_availability( $start, $end )
		] );
	}

	/**
	 * Schedule a new appointment
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function create_new_appointment( \WP_REST_Request $request ) {

		$calendar = $this->get_object_from_request( $request );

		if ( ! $calendar->exists() ) {
			return self::ERROR_404();
		}

		$email = sanitize_email( $request->get_param( 'email' ) );
		$name  = sanitize_text_field( $request->get_param( 'name' ) );
		$phone = sanitize_text_field( $request->get_param( 'phone' ) );
		$name  = split_name( $name );

		$contact = get_contactdata( $email );

		if ( ! $contact ) {
			$contact = new Contact( [
				'email'      => $email,
				'first_name' => $name[0],
				'last_name'  => $name[1],
				'owner_id'   => $calendar->get_user_id()
			] );
		} else {
			$contact->update( [
				'email'      => $email,
				'first_name' => $name[0],
				'last_name'  => $name[1],
				'owner_id'   => $calendar->get_user_id()
			] );
		}

		if ( $phone ) {
			$contact->update_meta( 'primary_phone', $phone );
		}

		$start = absint( $request->get_param( 'start' ) );
		$notes = sanitize_textarea_field( $request->get_param( 'notes' ) );

		$appointment = $calendar->schedule_appointment( $contact, [
			'start_time' => $start,
			'notes'      => $notes,
		] );

		if ( ! $appointment || ! $appointment->exists() ) {
			return self::ERROR_403();
		}

		after_form_submit_handler( $contact );

		return self::SUCCESS_RESPONSE( [
			'message'     => wpautop( do_replacements( $calendar->get_meta( 'message' ), $contact ) ),
			'appointment' => [
				'uuid' => $appointment->uuid,
			],
			'links'       => [
				'google' => $appointment->get_add_to_google_link(),
				'ics'    => $appointment->get_ics_link()
			]
		] );
	}

	public function public_permissions_callback( WP_REST_Request $request ) {
		return wp_verify_nonce( $request->get_header( 'x-wp-nonce' ), 'wp_rest' );
	}

}

