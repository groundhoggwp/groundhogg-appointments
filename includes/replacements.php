<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Event;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use function Groundhogg\cache_set_last_changed;
use function Groundhogg\convert_to_local_time;
use function Groundhogg\event_queue;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_db;
use function Groundhogg\html;
use function Groundhogg\managed_page_url;
use function Groundhogg\replacements;

/**
 * Created by PhpStorm.
 * User: atty
 * Date: 08-Jul-19
 * Time: 2:24 PM
 */
class Replacements {

	/**
	 * @var Appointment
	 */
	protected $appointment;

	/**
	 * @var Event
	 */
	protected $event;

	public function __construct() {
		add_action( 'groundhogg/event/run/after', [ $this, 'clear' ] );
	}

	/**
	 * Clear any cached appointment info.
	 */
	public function clear() {
		$this->appointment = null;
		$this->event       = null;
	}

	/**
	 * The replacement codes...
	 *
	 * @return array
	 */
	public function get_replacements() {
		return [
			[
				'name'        => __( 'Time to appointment', 'groundhogg-calendar' ),
				'code'        => 'time_to_appointment',
				'callback'    => [ $this, 'time_to_appointment' ],
				'description' => __( 'The time difference to the start of the appointment', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Contact Appointment Start Time', 'groundhogg-calendar' ),
				'code'        => 'appointment_start_time',
				'callback'    => [ $this, 'start_time' ],
				'description' => __( 'Returns the start date & time of a contact\'s appointment.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Contact Appointment End Time', 'groundhogg-calendar' ),
				'code'        => 'appointment_end_time',
				'callback'    => [ $this, 'end_time' ],
				'description' => __( 'Returns the end date & time of a contact\'s appointment.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Admin Appointment Start Time', 'groundhogg-calendar' ),
				'code'        => 'appointment_start_time_admin',
				'callback'    => [ $this, 'start_time_admin' ],
				'description' => __( 'Returns the start date & time of a contact\'s appointment in the admin\'s timezone.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Admin Appointment End Time', 'groundhogg-calendar' ),
				'code'        => 'appointment_end_time_admin',
				'callback'    => [ $this, 'end_time_admin' ],
				'description' => __( 'Returns the end date & time of a contact\'s appointment in the admin\'s timezone.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Appointment Actions', 'groundhogg-calendar' ),
				'code'        => 'appointment_actions',
				'callback'    => [ $this, 'appointment_actions' ],
				'description' => __( 'Links to allow cancelling or re-scheduling appointments.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Appointment Reschedule Link', 'groundhogg-calendar' ),
				'code'        => 'appointment_reschedule_link',
				'callback'    => [ $this, 'appointment_reschedule_link' ],
				'description' => __( 'Link to re-scheduling the appointment.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Appointment Cancel Link', 'groundhogg-calendar' ),
				'code'        => 'appointment_cancel_link',
				'callback'    => [ $this, 'appointment_cancel_link' ],
				'description' => __( 'Link to cancel the appointment.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Appointment Notes', 'groundhogg-calendar' ),
				'code'        => 'appointment_notes',
				'callback'    => [ $this, 'appointment_notes' ],
				'description' => __( 'Any notes about the appointment.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Google Meet URL', 'groundhogg-calendar' ),
				'code'        => 'google_meet_url',
				'callback'    => [ $this, 'google_meet_url' ],
				'description' => __( 'Google Meet meeting URL. (Needs Google Meet Enabled)', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Zoom Meeting Details', 'groundhogg-calendar' ),
				'code'        => 'zoom_meeting_details',
				'callback'    => [ $this, 'zoom_meeting_details' ],
				'description' => __( 'Detail Description about zoom meeting. (Needs zoom enabled and synced)', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Calender Owner First Name', 'groundhogg-calendar' ),
				'code'        => 'calendar_owner_first_name',
				'callback'    => [ $this, 'calendar_owner_first_name' ],
				'description' => __( 'First name of calendar owner.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Calender Owner Last Name', 'groundhogg-calendar' ),
				'code'        => 'calendar_owner_last_name',
				'callback'    => [ $this, 'calendar_owner_last_name' ],
				'description' => __( 'Last name of calendar owner.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Calender Owner Email', 'groundhogg-calendar' ),
				'code'        => 'calendar_owner_email',
				'callback'    => [ $this, 'calendar_owner_email' ],
				'description' => __( 'Email address of calendar owner.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Calender Owner Phone Number', 'groundhogg-calendar' ),
				'code'        => 'calendar_owner_phone',
				'callback'    => [ $this, 'calendar_owner_phone' ],
				'description' => __( 'Phone number of calendar owner.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Calender Owner Signature', 'groundhogg-calendar' ),
				'code'        => 'calendar_owner_signature',
				'callback'    => [ $this, 'calendar_owner_signature' ],
				'description' => __( 'Signature of calendar owner.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Calendar Name', 'groundhogg-calendar' ),
				'code'        => 'calender_name',
				'callback'    => [ $this, 'calender_name' ],
				'description' => __( 'The name of the calendar.', 'groundhogg-calendar' ),
			],
			[
				'name'        => __( 'Calendar Link', 'groundhogg-calendar' ),
				'code'        => 'calender_link',
				'callback'    => [ $this, 'calender_link' ],
				'description' => __( 'Links to the booking calendar.', 'groundhogg-calendar' ),
			],

		];
	}

	/**
	 * @return bool|Appointment
	 */
	protected function get_appointment() {

		if ( isset( $this->appointment ) ) {
			return $this->appointment;
		}

		if ( $event = event_queue()->get_current_event() ) {

			$this->event = $event;

			// If is a reminder event
			if ( in_array( $event->get_event_type(), [
				Email_Reminder::NOTIFICATION_TYPE,
				SMS_Reminder::NOTIFICATION_TYPE
			] ) ) {
				$this->set_appointment( new Appointment( $event->get_funnel_id() ) );

				return $this->appointment;
			}

			// Otherwise get contact's most recent appointment...
			$appts = get_db( 'appointments' )->query( [ 'contact_id' => $event->get_contact_id(), 'limit' => 1 ] );

			if ( ! empty( $appts ) ) {

				$last_booked       = array_shift( $appts );
				$this->set_appointment( new Appointment( $last_booked ) );

				return $this->appointment;
			}

		}

		return false;
	}

	/**
	 * @param Appointment $appointment
	 */
	public function set_appointment( Appointment $appointment ) {
		$this->appointment = $appointment;

		if ( function_exists( '\Groundhogg\cache_set_last_changed' ) ){
			// expire replacements cache so that new appt info is shown
			cache_set_last_changed( 'replacements' );
		} else {
			wp_cache_set( 'last_changed', microtime(), 'replacements' );
		}
	}

	public function time_to_appointment() {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		return human_time_diff( time(), $this->get_appointment()->get_start_time() );
	}

	/**
	 * Get the appointment start time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function start_time( $contact_id = 0 ) {

		if ( ! $this->get_appointment() ) {
			return false;
		}

		$contact = get_contactdata( $contact_id );

		$local_time = $contact->get_local_time( $this->get_appointment()->get_start_time() );

		$format = get_date_time_format();

		return date_i18n( $format, $local_time );
	}

	/**
	 * Get the appointment start time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function start_time_admin( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$local_time = convert_to_local_time( $this->get_appointment()->get_start_time() );
		$format     = get_date_time_format();

		return date_i18n( $format, $local_time );
	}

	/**
	 * Get the appointment end time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function end_time( $contact_id = 0 ) {

		if ( ! $this->get_appointment() ) {
			return false;
		}

		$contact    = get_contactdata( $contact_id );
		$local_time = $contact->get_local_time( $this->get_appointment()->get_end_time() );
		$format     = get_date_time_format();


		return date_i18n( $format, $local_time );
	}

	/**
	 * Get the appointment end time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function end_time_admin( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$local_time = convert_to_local_time( $this->get_appointment()->get_end_time() );
		$format     = get_date_time_format();

		return date_i18n( $format, $local_time );
	}

	/**
	 * Get the appointment end time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function appointment_notes( $contact_id = 0 ) {

		if ( ! $this->get_appointment() ) {
			return false;
		}

		return wpautop( $this->get_appointment()->get_details() );
	}

	public function cancellation_reason() {

	}

	public function reschedule_reason() {

	}


	/**
	 * Insert appointment management links.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function appointment_actions( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$actions = [
			html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'reschedule' ) ], __( 'Reschedule', 'groundhogg-calendar' ) ),
			html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'cancel' ) ], __( 'Cancel' ) ),
		];

		return implode( ' | ', $actions );
	}

	/**
	 * Insert appointment management links.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function appointment_reschedule_link( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		return html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'reschedule' ) ], __( 'Reschedule', 'groundhogg-calendar' ) );
	}

	/**
	 * Insert appointment management links.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function appointment_cancel_link( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		return html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'cancel' ) ], __( 'Cancel', 'groundhogg-calendar' ) );
	}

	/**
	 * fetch Zoom meting description from zoom
	 *
	 * @return bool|string
	 */
	public function zoom_meeting_details() {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		return wpautop( $this->get_appointment()->get_zoom_meeting_details() );
	}


	/**
	 * fetch Zoom meting description from zoom
	 *
	 * @return bool|string
	 */
	public function google_meet_url() {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		return $this->get_appointment()->get_meta( 'google_meet_url' );

	}

	/**
	 * Get the appointment end time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function calendar_owner_first_name( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$owner = $this->get_appointment()->get_owner();

		return $owner->first_name;
	}

	/**
	 * Get the appointment end time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function calendar_owner_last_name( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$owner = $this->get_appointment()->get_owner();

		return $owner->last_name;
	}

	/**
	 * Get the appointment end time.
	 *
	 * @param int $contact_id
	 *
	 * @return bool|string
	 */
	public function calendar_owner_email( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$owner = $this->get_appointment()->get_owner();

		return $owner->user_email;
	}

	/**
	 * Get the owners phone number
	 *
	 * @param int $contact_id
	 *
	 * @return false|int|mixed|string
	 */
	public function calendar_owner_phone( $contact_id = 0 ) {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$owner = $this->get_appointment()->get_owner();

		return $owner || replacements()->replacement_business_phone() ;
	}

	/**
	 * Find the owner's signature
	 *
	 * @return false|int|mixed|string
	 */
	public function calendar_owner_signature() {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		$owner = $this->get_appointment()->get_owner();

		return $owner->signature;
	}

	/**
	 * Retrieve the calendar link
	 *
	 * @return false|string|void
	 */
	public function calender_link() {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		return managed_page_url( sprintf( 'calendar/%s/', $this->get_appointment()->get_calendar()->slug ) );
	}

	/**
	 * Retrieve the calendar link
	 *
	 * @return false|string|void
	 */
	public function calender_name() {
		if ( ! $this->get_appointment() ) {
			return false;
		}

		return $this->get_appointment()->get_calendar()->get_name();
	}

}
