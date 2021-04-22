<?php

namespace GroundhoggBookingCalendar\Classes;


use Groundhogg\Base_Object_With_Meta;
use function Groundhogg\do_replacements;
use function Groundhogg\encrypt;
use Groundhogg\Event;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_db;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\DB\Calendar_Meta;
use \Google_Service_Calendar_Event;
use \Google_Service_Calendar;
use \Exception;
use function Groundhogg\get_request_var;
use function Groundhogg\managed_page_url;
use function Groundhogg\utils;
use function GroundhoggBookingCalendar\get_google_client;
use function GroundhoggBookingCalendar\get_in_time_zone;
use function GroundhoggBookingCalendar\google_calendar;
use function GroundhoggBookingCalendar\is_sms_plugin_active;
use function GroundhoggBookingCalendar\send_reminder_notification;
use function GroundhoggBookingCalendar\send_sms_reminder_notification;
use function GroundhoggBookingCalendar\zoom;

class Appointment extends Base_Object_With_Meta {
	protected function get_meta_db() {
		return Plugin::$instance->dbs->get_db( 'appointmentmeta' );
	}

	protected function post_setup() {
		// TODO: Implement post_setup() method.
	}

	protected function get_db() {
		return Plugin::$instance->dbs->get_db( 'appointments' );
	}

	protected function get_object_type() {
		return 'appointment';
	}

	/**
	 * Get the start time in pretty format.
	 *
	 * @param bool $zone whether to return as the timezone of the contact
	 *
	 * @return string
	 */
	public function get_pretty_start_time( $zone = false ) {

		$time = $this->get_start_time();

		if ( $zone ) {

			$time_zone = $this->get_contact()->get_time_zone();

			if ( $time_zone ) {
				$time = get_in_time_zone( $time, $time_zone );
			}

		}

		return date_i18n( get_date_time_format(), $time );
	}

	/**
	 * Returns appointment id
	 *
	 * @return int
	 */
	public function get_id() {
		return absint( $this->ID );
	}

	/**
	 * @return false|\Groundhogg\Contact
	 */
	public function get_contact() {
		return get_contactdata( $this->get_contact_id() );
	}

	/**
	 * Return contact id
	 *
	 * @return int
	 */
	public function get_contact_id() {
		return absint( $this->contact_id );
	}

	/**
	 * @return int
	 */
	public function get_owner_id() {
		return $this->get_calendar()->get_user_id();
	}

	/**
	 * Return calendar id
	 *
	 * @return int
	 */
	public function get_calendar_id() {
		return absint( $this->calendar_id );
	}

	protected $calendar = null;

	/**
	 * @return Calendar
	 */
	public function get_calendar() {
		if ( $this->calendar ) {
			return $this->calendar;
		}

		$this->calendar = new Calendar( $this->get_calendar_id() );

		return $this->calendar;
	}

	/**
	 * Return name of appointment
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}


	public function get_status() {
		return $this->status;
	}

	/**
	 * Return start time of appointment
	 *
	 * @return int
	 */
	public function get_start_time() {
		return absint( $this->start_time );
	}

	/**
	 * Return end time of appointment
	 *
	 * @return int
	 */
	public function get_end_time() {
		return absint( $this->end_time );
	}

	/**
	 * Update google as well.
	 *
	 * @param array $data
	 *
	 * @return bool
	 */
	public function update( $data = [] ) {
		$status = parent::update( $data );

		if ( ! $status ) {
			return false;
		}

		/**
		 * updates the zoom meeting if there is one
		 */
		$this->update_zoom_meeting();
		$this->update_in_google();
		
		return true;
	}

	/**
	 * create appointment id for google events.
	 *
	 * @return string
	 */
	protected function get_google_appointment_id() {
		return 'ghcalendarcid' . $this->get_calendar_id() . 'aid' . $this->get_id();
	}

	/**
	 * Delete appointment.
	 *  Sends cancel email
	 *  Deletes appointment from the google calendar
	 *  Cancels all the pending events for the appointment
	 *
	 * @return bool
	 */
	public function delete() {

		$this->cancel();
		$this->delete_zoom_meeting();
		$this->delete_in_google();

		return parent::delete();
	}

	public function get_full_calendar_event() {

		if ( $this->get_status() === 'cancelled' ) {
			$color = '#dc3545';
		} else if ( $this->get_status() == 'approved' ) {
			$color = '#28a745';
		} else {
			$color = '#0073aa';
		}

		return [
			'id'         => $this->get_id(),
			'title'      => $this->get_name(),
			'start'      => utils()->date_time->convert_to_local_time( (int) $this->get_start_time() ) * 1000,
			'end'        => utils()->date_time->convert_to_local_time( (int) $this->get_end_time() ) * 1000,
			'constraint' => 'businessHours',
			'editable'   => true,
			'allDay'     => false,
			'color'      => $color,
			'url'        => admin_url( 'admin.php?page=gh_calendar&action=edit_appointment&appointment=' . $this->get_id() )
		];

	}

	/**
	 *
	 * Book the appointment
	 * Schedules all the reminder emails....
	 */
	public function book() {
		// EMAIL TIME...
		do_action( 'groundhogg/calendar/appointment/book/before' );
		$this->schedule_reminders( Reminder::BOOKED );
		do_action( 'groundhogg/calendar/appointment/book/after' );
	}


	/**
	 * Reschedule Appointment
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	public function reschedule( $args ) {
		// update appointment
		$args = wp_parse_args( $args, [
			'contact_id'  => $this->get_contact_id(),
			'calendar_id' => $this->get_calendar_id(),
			'name'        => $this->get_name(),
			'status'      => 'pending',
			'start_time'  => $this->get_start_time(),
			'end_time'    => $this->get_end_time(),
			'notes'       => $this->get_meta( 'notes', true )
		] );

		$start = $this->get_start_time();
		$end   = $this->get_end_time();

		$note = $args['notes'];
		unset( $args['notes'] );

		if ( $note ) {
			$this->update_meta( 'notes', $note );
		}

		$status = $this->update( $args );

		if ( ! $status ) {
			return false;
		}


		// match the dates before performing the operation..

		if ( $start !== get_array_var( $args, 'start_time' ) || $end !== get_array_var( $args, 'end_time' ) ) {

			//cancel events form the event queue
			$this->cancel_reminders();

			// Schedule Appointment Booked Email...
			do_action( 'groundhogg/calendar/appointment/reschedule/before' );

			$this->schedule_reminders( Reminder::RESCHEDULED );

			do_action( 'groundhogg/calendar/appointment/reschedule/after' );

			do_action( 'groundhogg/calendar/appointment/reschedule', $this->get_id(), Reminder::RESCHEDULED );

		}

		return true;
	}


	/**
	 * Cancel appointment and send reminder of canceling event..
	 *
	 * @return bool
	 */
	public function cancel() {
		$status = $this->update( [
			'status' => 'cancelled'
		] );

		if ( ! $status ) {
			return false;
		}

		$this->cancel_reminders();

		do_action( 'groundhogg/calendar/appointment/cancelled/before' );
		$this->schedule_reminders( Reminder::CANCELLED );
		do_action( 'groundhogg/calendar/appointment/cancelled/after' );

		do_action( 'groundhogg/calendar/appointment/cancelled', $this->get_id(), Reminder::CANCELLED );

		return true;

	}

	/**
	 * @deprecated
	 * @return bool
	 */
	public function approve() {

		$status = $this->update( [
			'status' => 'approved'
		] );

		if ( ! $status ) {
			return false;
		}

//        $this->cancel_reminders();

		do_action( 'groundhogg/calendar/appointment/approve/before' );
		$this->schedule_reminders( Reminder::APPROVED );
		do_action( 'groundhogg/calendar/appointment/approve/after' );

		do_action( 'groundhogg/calendar/appointment/approve', $this->get_id(), Reminder::APPROVED );

		return true;
	}

	protected function schedule_reminders( $which ) {

		if ( is_sms_plugin_active() ) {
			if ( $this->get_calendar()->get_meta( 'sms_notification' ) ) {
				$this->schedule_sms_reminders( $which );
			}
		}

		// Schedule Appointment Booked Email...
		if ( $booked_email_id = $this->get_calendar()->get_notification_emails( $which ) ) {
			send_reminder_notification( absint( $booked_email_id ), absint( $this->get_id() ), time() );
		}

		if ( ! ( $which === Reminder::CANCELLED ) ) {

			// Schedule Email Reminders...
			$reminders = $this->get_calendar()->get_reminder_emails();

			if ( empty( $reminders ) ) {
				return;
			}

			foreach ( $reminders as $reminder ) {

				// Calc time...
				switch ( $reminder['when'] ) {
					default:
					case 'before':
						$time = strtotime( sprintf( "-%d %s", $reminder['number'], $reminder['period'] ), $this->get_start_time() );
						if ( $time > time() ) {
							send_reminder_notification( $reminder['email_id'], $this->get_id(), $time );
						}
						break;
					case 'after':
						$time = strtotime( sprintf( "+%d %s", $reminder['number'], $reminder['period'] ), $this->get_end_time() );
						if ( $time > time() ) {
							send_reminder_notification( $reminder['email_id'], $this->get_id(), $time );
						}
						break;
				}

			}
		}
	}

	protected function cancel_reminders() {
		// delete all the waiting events for the appointment
		$events = get_db( 'event_queue' )->query( [
			'funnel_id'  => $this->get_id(),
			'contact_id' => $this->get_contact_id(),
			'event_type' => Reminder::NOTIFICATION_TYPE,
			'status'     => 'waiting',
		] );

		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {
				$eve = new Event( absint( $event->ID ), 'event_queue' );
				$eve->update( [ 'status' => 'cancelled', ] );
			}
		}

		$this->cancel_sms_reminders();
	}

	/**
	 * Schedule any SMS reminders if applicable
	 *
	 * @param $which
	 */
	protected function schedule_sms_reminders( $which ) {

		// Schedule Appointment Booked sms...
		if ( $booked_sms_id = $this->get_calendar()->get_notification_sms( $which ) ) {
			send_sms_reminder_notification( absint( $booked_sms_id ), absint( $this->get_id() ), time() );
		}

		if ( ! ( $which === SMS_Reminder::CANCELLED ) ) {

			// Schedule sms Reminders...
			$sms_reminders = $this->get_calendar()->get_reminder_sms();

			if ( empty( $sms_reminders ) ) {
				return;
			}

			foreach ( $sms_reminders as $reminder ) {

				// Calc time...
				switch ( $reminder['when'] ) {
					default:
					case 'before':
						$time = strtotime( sprintf( "-%d %s", $reminder['number'], $reminder['period'] ), $this->get_start_time() );
						if ( $time > time() ) {
							send_sms_reminder_notification( absint( $reminder['sms_id'] ), $this->get_id(), $time );
						}
						break;
					case 'after':
						$time = strtotime( sprintf( "+%d %s", $reminder['number'], $reminder['period'] ), $this->get_end_time() );
						if ( $time > time() ) {
							send_sms_reminder_notification( absint( $reminder['sms_id'] ), $this->get_id(), $time );
						}
						break;
				}

			}
		}
	}


	protected function cancel_sms_reminders() {
		// delete all the waiting events for the appointment
		$events = get_db( 'event_queue' )->query( [
			'funnel_id'  => $this->get_id(),
			'contact_id' => $this->get_contact_id(),
			'event_type' => SMS_Reminder::NOTIFICATION_TYPE,
			'status'     => 'waiting',
		] );

		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {
				$eve = new Event( absint( $event->ID ), 'event_queue' );
				$eve->update( [ 'status' => 'cancelled', ] );
			}
		}
	}


	/**
	 * Return a manage link to the appoitment.
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	public function manage_link( $action = 'cancel' ) {
		return managed_page_url( sprintf( 'appointment/%s/%s', urlencode( encrypt( $this->get_id() ) ), $action ) );
	}

	/**
	 * Return zoom meeting id
	 *
	 * @return int
	 */
	public function get_zoom_meeting_id() {
		return absint( $this->get_meta( 'zoom_id', true ) );
	}

	/**
	 * Create the zoom meeting if the zoom integration is enabled
	 */
	public function create_zoom_meeting() {
		if ( ! $this->get_calendar()->is_zoom_enabled() ) {
			return;
		}

		$details = [
			"topic"      => $this->get_calendar()->get_name() . ' - Meeting with ' . get_contactdata( $this->get_contact_id() )->get_full_name(),
			"type"       => 2,
			"start_time" => date( 'Y-m-d\TH:i:s', $this->get_start_time() ) . 'Z',
			"duration"   => $this->get_calendar()->get_appointment_length( true ) / 60,
		];

		$response = zoom()->request( $this->get_calendar()->get_zoom_access_token( true ), 'users/me/meetings', $details );

		if ( $response->id ) {
			$this->update_meta( 'zoom_id', $response->id );
		}
	}

	/**
	 * Update an existing zoom meeting
	 */
	public function update_zoom_meeting() {

		if ( ! $this->get_calendar()->is_zoom_enabled() ) {
			return;
		} else if ( ! $this->get_zoom_meeting_id() ) {
			$this->create_zoom_meeting();

			return;
		}

		$details = [
			"topic"      => $this->get_calendar()->get_name() . ' - Meeting with ' . get_contactdata( $this->get_contact_id() )->get_full_name(),
			"type"       => 2,
			"start_time" => date( 'Y-m-d\TH:i:s', $this->get_start_time() ) . 'Z',
			"duration"   => $this->get_calendar()->get_appointment_length( true ) / 60,
		];

		zoom()->request( $this->get_calendar()->get_zoom_access_token( true ), 'meetings/' . $this->get_zoom_meeting_id(), $details );
	}

	/**
	 * Delete any existing zoom meeting
	 */
	public function delete_zoom_meeting() {
		if ( ! $this->get_calendar()->is_zoom_enabled() || ! $this->get_zoom_meeting_id() ) {
			return;
		}

		// ensure that this appointment actually has a zoom meeting
		zoom()->request(
			$this->get_calendar()->get_zoom_access_token( true ),
			'meetings/' . $this->get_zoom_meeting_id(),
			null,
			'DELETE'
		);
	}

	/**
	 * Gets the zoom meeting invitation
	 *
	 * @return string|void
	 */
	public function get_zoom_meeting_details() {
		if ( ! $this->get_calendar()->is_zoom_enabled() ) {
			return __( 'Zoom is not enabled.', 'groundhogg-calendar' );
		}

		$access_token = $this->get_calendar()->get_zoom_access_token();

		if ( ! $access_token || is_wp_error( $access_token ) ) {
			return '';
		}

		// create meeting if meeting does not exist
		if ( ! $this->get_zoom_meeting_id() ) {
			return __( 'Zoom meeting details not found!', 'groundhogg-calendar' );
		}

		$endpoint = 'meetings/' . $this->get_zoom_meeting_id() . '/invitation';

		$response = zoom()->request( $access_token, $endpoint, null, 'GET' );

		if ( $response->invitation ) {
			return $response->invitation;
		}

		return __( 'Zoom meeting details not found!', 'groundhogg-calendar' );
	}

	/**
	 * Add the appointment in the Google
	 */
	public function add_in_google() {

		if ( ! $this->get_calendar()->is_connected_to_google() ) {
			return;
		}

		$client  = $this->get_calendar()->get_google_client();
		$service = new Google_Service_Calendar( $client );

		if ( ! google_calendar()->is_valid_calendar( $this->get_calendar()->get_google_calendar_id(), $service ) ) {
			return;
		}

		\GroundhoggBookingCalendar\Plugin::$instance->replacements->set_appointment( $this );

		$summary = $this->get_name();

		if ( $this->get_calendar()->get_meta( 'google_appointment_name' ) ) {
			$summary = do_replacements( $this->get_calendar()->get_meta( 'google_appointment_name' ), $this->get_contact_id() );
		}

		$description = $this->get_meta( 'notes' );
		if ( $this->get_calendar()->get_meta( 'google_appointment_description' ) ) {
			$description = do_replacements( $this->get_calendar()->get_meta( 'google_appointment_description' ), $this->get_contact_id() );
		}

		$contact = get_contactdata( $this->get_contact_id() );

		// building the request object
		$event_data = [
			'id'          => $this->get_google_appointment_id(),
			'summary'     => $summary,
			'description' => $description,
			'start'       => [
				'dateTime' => date( DATE_RFC3339, $this->get_start_time() ),
				'timeZone' => 'UTC'
			],
			'end'         => [
				'dateTime' => date( DATE_RFC3339, $this->get_end_time() ),
				'timeZone' => 'UTC'
			],
			'attendees'   => [
				[ 'email' => $contact->get_email() ],
			],

		];

		if ( $this->get_calendar()->is_google_meet_enabled() ) {
			$event_data["conferenceData"] = [
				"createRequest" => [
					"requestId"             => $this->get_id(),
					"conferenceSolutionKey" => [
						"type" => 'hangoutsMeet'
					],
					'status'                => [
						'statusCode' => 'success'
					]
				]
			];
		}

		$event = new Google_Service_Calendar_Event( $event_data );

		try {
			$event_created = $service->events->insert( $this->get_calendar()->get_google_calendar_id(), $event, [ 'conferenceDataVersion' => 1 ] );

			if ( $event_created->hangoutLink ) {
				$this->add_meta( 'google_meet_url', $event_created->hangoutLink );
			}
		} catch ( Exception $e ) {
		}
	}

	/**
	 * Update in google
	 *
	 * @return bool
	 */
	protected function update_in_google() {

		if ( ! $this->get_calendar()->is_connected_to_google() ) {
			return false;
		}

		// create google client
		$client = $this->get_calendar()->get_google_client();

		$service = new Google_Service_Calendar( $client );

		if ( ! google_calendar()->is_valid_calendar( $this->get_calendar()->get_google_calendar_id(), $service ) ) {
			return false;
		}

		\GroundhoggBookingCalendar\Plugin::$instance->replacements->set_appointment( $this );

		$contact = get_contactdata( $this->get_contact_id() );

		$google_appointment_id = $this->get_google_appointment_id();
		$description           = $this->get_meta( 'notes', true );
		if ( $this->get_calendar()->get_meta( 'google_appointment_description' ) ) {
			$description = do_replacements( $this->get_calendar()->get_meta( 'google_appointment_description' ), $this->get_contact_id() );
		}
		$event = new Google_Service_Calendar_Event( array(
			'id'          => $google_appointment_id,
			'summary'     => $this->get_name(),
			'description' => $description,
			'start'       => [
				'dateTime' => date( DATE_RFC3339, $this->get_start_time() ),
				'timeZone' => 'UTC'
			],
			'end'         => [
				'dateTime' => date( DATE_RFC3339, $this->get_end_time() ),
				'timeZone' => 'UTC'
			],
			'attendees'   => array(
				array( 'email' => $contact->get_email() ),
			),
		) );

		try {
			$updatedEvent = $service->events->update( $this->get_calendar()->get_google_calendar_id(), $google_appointment_id, $event, [] );
		} catch ( \Exception $exception ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete Appointment from google calendar if it exist.
	 */
	public function delete_in_google() {

		if ( ! $this->get_calendar()->is_connected_to_google() ) {
			return;
		}

		$client  = $this->get_calendar()->get_google_client();
		$service = new Google_Service_Calendar( $client );
		try {
			$service->events->delete( $this->get_calendar()->get_google_calendar_id(), $this->get_google_appointment_id() );
		} catch ( Exception $e ) {
		}
	}

}