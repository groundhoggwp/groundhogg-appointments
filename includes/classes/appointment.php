<?php

namespace GroundhoggBookingCalendar\Classes;


use \Exception;
use Google\Model;
use Groundhogg\Plugin;
use \Google_Service_Calendar;
use \Google_Service_Calendar_Event;
use Groundhogg\Base_Object_With_Meta;
use GroundhoggBookingCalendar\ICS;
use function Groundhogg\get_db;
use function Groundhogg\utils;
use function Groundhogg\encrypt;
use function Groundhogg\do_replacements;
use function Groundhogg\get_contactdata;
use function Groundhogg\managed_page_url;
use function Groundhogg\Ymd_His;
use function Groundhogg\minify_html;
use function GroundhoggBookingCalendar\generate_uuid;
use function GroundhoggBookingCalendar\get_date_format;
use function GroundhoggBookingCalendar\get_time_format;
use function GroundhoggBookingCalendar\sanitize_google_uuid;
use function GroundhoggBookingCalendar\zoom;
use function Groundhogg\get_date_time_format;
use function GroundhoggBookingCalendar\get_in_time_zone;

class Appointment extends Base_Object_With_Meta {

	/**
	 * @var \WP_User
	 */
	private $owner;
	public $g_uuid;

	protected function get_meta_db() {
		return Plugin::$instance->dbs->get_db( 'appointmentmeta' );
	}

	protected function post_setup() {
		$this->g_uuid = sanitize_google_uuid( $this->uuid );
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

		$time = $this->get_start_time( $zone === 'admin' );

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

	/**
	 * @return false|\WP_User
	 */
	public function get_owner() {

		if ( ! $this->owner ) {
			$this->owner = get_userdata( $this->get_calendar()->get_user_id() );
		}

		return $this->owner;
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
		return sprintf( _x( '%s and %s %s', 'Appointment Name', 'groundhogg-calendar' ), $this->get_contact()->get_full_name(), $this->get_owner()->first_name, $this->get_owner()->last_name );
	}

	public function get_status() {
		return $this->status;
	}

	public function is_cancelled() {
		return $this->get_status() === 'cancelled';
	}

	/**
	 * Return start time of appointment
	 *
	 * @return int
	 */
	public function get_start_time( $local = false ) {
		return $local ? utils()->date_time->convert_to_local_time( absint( $this->start_time ) ) : absint( $this->start_time );
	}

	/**
	 * Return end time of appointment
	 *
	 * @return int
	 */
	public function get_end_time( $local = false ) {
		return $local ? utils()->date_time->convert_to_local_time( absint( $this->end_time ) ) : absint( $this->end_time );
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

		// Delete in synced events if it exists there
		get_db( 'synced_events' )->delete( [
			'event_id' => $this->g_uuid
		] );

		return parent::delete();
	}

	/**
     * The owner name
     *
	 * @return string
	 */
    public function get_owner_name(){
	    return $this->get_owner()->first_name . ' ' . $this->get_owner()->last_name;
    }

	/**
	 * Retreives in Full callendar event format
	 *
	 * @return array
	 */
	public function get_for_full_calendar() {
		return [
			'id'            => $this->uuid,
			'local_id'      => $this->get_id(),
			'title'         => sprintf( __( '%s and %s' ), $this->get_contact()->get_full_name(), $this->get_owner_id() === get_current_user_id() ? __('You') : $this->get_owner_name() ) ,
			'start'         => (int) $this->get_start_time() * 1000,
			'end'           => (int) $this->get_end_time() * 1000,
//			'editable'      => true,
			'allDay'        => false,
			'color'         => $this->get_status() === 'scheduled' ? '#28a745' : '#dc3545',
			'classNames'    => [ $this->is_cancelled() ? 'cancelled' : 'scheduled' ],
			'extendedProps' => [
				'appointment' => $this,
			]
		];

	}

	public function get_as_array() {

		$startSateTime = new \DateTime( 'now', wp_timezone() );
		$endDateTime   = new \DateTime( 'now', wp_timezone() );
		$startSateTime->setTimestamp( $this->get_start_time() );
		$endDateTime->setTimestamp( $this->get_end_time() );

		return array_merge( parent::get_as_array(), [
			'contact' => $this->get_contact(),
			'i18n'    => [
				'dateFrom'  => $startSateTime->format( get_date_format() ),
				'dateTo'    => $endDateTime->format( get_date_format() ),
				'from'      => $startSateTime->format( get_time_format() ),
				'to'        => $endDateTime->format( get_time_format() ),
				'ownerName' =>  $this->get_owner_id() === get_current_user_id() ? __('You') : $this->get_owner_name(),
			]
		] );
	}

	/**
	 *
	 * Book the appointment
	 * Schedules all the reminder emails....
	 */
	public function schedule() {

		$this->create_zoom_meeting();
		$this->add_in_google();

		/**
		 * Runs if the appointment was initially scheduled
		 *
		 * @param $appointment Appointment
		 */
		do_action( 'groundhogg/calendar/appointment/scheduled', $this );
	}

	/**
	 * Reschedule Appointment
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	public function reschedule( $args ) {

		$args = wp_parse_args( $args, [
			'contact_id'  => $this->get_contact_id(),
			'calendar_id' => $this->get_calendar_id(),
			'status'      => 'scheduled',
			'start_time'  => $this->get_start_time(),
			'end_time'    => $this->get_end_time(),
		] );

		$orig_start = $this->get_start_time();
		$orig_end   = $this->get_end_time();

		$status = $this->update( $args );

		if ( ! $status ) {
			return false;
		}

		// match the dates before performing the operation..
		if ( $orig_start !== $this->get_start_time() || $orig_end !== $this->get_end_time() ) {

			get_db( 'synced_events' )->update( [
				'event_id' => $this->g_uuid
			], [
				'start_time'        => $this->get_start_time(),
				'end_time'          => $this->get_end_time(),
				'start_time_pretty' => Ymd_His( $this->get_start_time() ),
				'end_time_pretty'   => Ymd_His( $this->get_end_time() ),
			] );

			/**
			 * Runs if the appointment was rescheduled
			 *
			 * @param $appointment Appointment
			 * @param $orig_start  int The original time
			 * @param $orig_end    int The original time
			 */
			do_action( 'groundhogg/calendar/appointment/rescheduled', $this, $orig_start, $orig_end );
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

		/**
		 * Runs if the appointment was cancelled
		 *
		 * @param $appointment Appointment
		 */
		do_action( 'groundhogg/calendar/appointment/cancelled', $this );

		return true;

	}

	/**
	 * Return a manage link to the appointment.
	 *
	 * @param string $action
	 *
	 * @return string
	 */
	public function manage_link( $action = 'cancel' ) {

		switch ( $action ) {
			case 'reschedule':
			case 'cancel':
				return managed_page_url( sprintf( 'appointment/%s/#/%s/', $this->uuid, $action ) );
			default:
				return managed_page_url( sprintf( 'appointment/%s/%s', $this->uuid, $action ) );
		}
	}

	/**
	 * Link to reschedule the appointment
	 *
	 * @return string
	 */
	public function reschedule_link() {
		return $this->manage_link( 'reschedule' );
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
			'topic'      => $this->get_name(),
			'type'       => 2,
			'start_time' => date( 'Y-m-d\TH:i:s', $this->get_start_time() ) . 'Z',
			'duration'   => $this->get_calendar()->get_appointment_length( true ) / MINUTE_IN_SECONDS,
		];

		$settings = apply_filters( 'groundhogg/appointments/zoom_settings', [] );

		if ( ! empty( $settings ) ) {
			$details['settings'] = $settings;
		}

		$response = zoom()->request( $this->get_calendar()->get_zoom_account_id(), 'users/me/meetings', $details );

		if ( is_wp_error( $response ) ) {
			$this->add_error( $response );
		}

		if ( $response->id ) {
			$this->update_meta( 'zoom_id', $response->id );
			$this->update_meta( 'zoom_join_url', $response->join_url );
		}

		$this->update_zoom_meeting_invitation();
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

		if ( $this->is_cancelled() ) {
			$this->delete_zoom_meeting();

			return;
		}

		$details = [
			'topic'      => $this->get_name(),
			'type'       => 2,
			'start_time' => date( 'Y-m-d\TH:i:s', $this->get_start_time() ) . 'Z',
			'duration'   => $this->get_calendar()->get_appointment_length( true ) / MINUTE_IN_SECONDS,
		];

		$settings = apply_filters( 'groundhogg/appointments/zoom_settings', [] );

		if ( ! empty( $settings ) ) {
			$details['settings'] = $settings;
		}

		zoom()->request( $this->get_calendar()->get_zoom_access_token( true ), 'meetings/' . $this->get_zoom_meeting_id(), $details, 'PATCH' );

		$this->update_zoom_meeting_invitation();
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

		$this->delete_meta( 'zoom_meeting_invitation' );
	}

	/**
	 * Update the Zoom meeting invitation details for this appt
	 *
	 * @return false|string|void
	 */
	public function update_zoom_meeting_invitation() {
		if ( ! $this->get_calendar()->is_zoom_enabled() ) {
			return false;
		}

		$endpoint = 'meetings/' . $this->get_zoom_meeting_id() . '/invitation';

		$response = zoom()->request( $this->get_calendar()->get_zoom_account_id(), $endpoint, null, 'GET' );

		if ( ! $response->invitation ) {
			return false;
		}

		$this->update_meta( 'zoom_meeting_invitation', $response->invitation );

		return $response->invitation;
	}

	/**
	 * Gets the zoom meeting invitation
	 *
	 * @return string|void
	 */
	public function get_zoom_meeting_details() {

		if ( ! $this->get_calendar()->is_zoom_enabled() ) {
			return false;
		}

		$invite = $this->get_meta( 'zoom_meeting_invitation' );

		if ( ! $invite && $this->get_zoom_meeting_id() ) {
			$invite = $this->update_zoom_meeting_invitation();
		}

		return $invite;
	}

	/**
	 * Get the full google description of the event including manage links.
	 *
	 * @return string
	 */
	public function get_details( $with_links = true ) {

		$description = $this->get_calendar()->get_description();

		if ( $this->get_calendar()->get_meta( 'additional_notes' ) ) {
			$description .= "\n\n<b>" . __( 'Additional Instructions:', 'groundhogg-calendar' ) . "</b>\n" .
			                do_replacements( $this->get_calendar()->get_meta( 'additional_notes' ), $this->get_contact() );
		}

		if ( $this->get_meta( 'notes' ) ) {
			$description .= "\n\n<b>" . __( 'Guest Notes:', 'groundhogg-calendar' ) . "</b>\n" . $this->get_meta( 'notes' );
		}

		if ( $this->get_calendar()->is_zoom_enabled() ) {
			$description .= "\n\n" . $this->get_zoom_meeting_details();
		}

		if ( $with_links ) {
			$description .= "\n\n" . __( 'Reschedule this appointment:', 'groundhogg-calendar' ) . ' ' . $this->reschedule_link();
			$description .= "\n\n" . __( 'Cancel this appointment:', 'groundhogg-calendar' ) . ' ' . $this->manage_link();
		}

		return $description;
	}

	/**
	 * Get the full google description of the event including manage links.
	 *
	 * @return string
	 */
	public function get_google_description() {

		ob_start();

		?>
        <p><?php _e( sprintf( __( 'Appointment Type: %s', 'groundhogg-calendar' ), $this->get_calendar()->get_name() ) ) ?></p>
		<?php echo wpautop( $this->get_calendar()->get_description() ) ?>
		<?php if ( $this->get_calendar()->get_meta( 'additional_notes' ) ) : ?>
            <p><b><?php _e( 'Additional Instructions:', 'groundhogg-calendar' ) ?></b></p>
			<?php echo wpautop( do_replacements( $this->get_calendar()->get_meta( 'additional_notes' ), $this->get_contact() ) ) ?>
		<?php endif; ?>
		<?php if ( $this->get_calendar()->get_meta( 'google_appointment_description' ) ) : ?>
			<?php echo wpautop( do_replacements( $this->get_calendar()->get_meta( 'google_appointment_description' ), $this->get_contact() ) ) ?>
		<?php endif; ?>
		<?php if ( $this->get_meta( 'notes' ) ) : ?>
			<?php echo wpautop( $this->get_meta( 'notes' ) ) ?>
		<?php endif; ?>
		<?php if ( $this->get_calendar()->is_zoom_enabled() ) : ?>
			<?php echo wpautop( $this->get_zoom_meeting_details() ) ?>
		<?php endif; ?>
        <p><a href="<?php echo $this->reschedule_link() ?>"><?php _e( 'Reschedule', 'groundhogg-calendar' ) ?></a> | <a
                    href="<?php echo $this->manage_link() ?>"><?php _e( 'Cancel', 'groundhogg-calendar' ) ?></a></p>
		<?php

		// Remove Newlines causing <br> in Google
		return minify_html( ob_get_clean() );
	}

	/**
	 * Get the appointment summary
	 *
	 * @return string
	 */
	public function get_google_summary() {
		$summary = $this->get_name();

		if ( $this->get_calendar()->get_meta( 'google_appointment_name' ) ) {
			$summary = do_replacements( $this->get_calendar()->get_meta( 'google_appointment_name' ), $this->get_contact() );
		}

		return $summary;
	}

	/**
	 * Get the google meet conference ID
	 *
	 * @return bool|mixed
	 */
	protected function get_google_meet_conference_id() {
		if ( ! $this->get_meta( 'google_meet_conference_id' ) ) {
			$this->update_meta( 'google_meet_conference_id', generate_uuid() );
		}

		return $this->get_meta( 'google_meet_conference_id' );
	}

	/**
	 * @return string
	 */
	public function get_location() {
		return do_replacements( $this->get_calendar()->get_meta( 'appointment_location' ) ?: '', $this->get_contact() );
	}

	/**
	 * @return array
	 */
	protected function get_google_event_format() {
		$event = [
			'id'          => $this->g_uuid,
			'summary'     => $this->get_google_summary(),
			'status'      => $this->is_cancelled() ? 'cancelled' : 'confirmed',
			'description' => $this->get_google_description(),
			'location'    => $this->get_calendar()->is_zoom_enabled() ? $this->get_meta( 'zoom_join_url' ) : $this->get_location(),
			'start'       => [
				'dateTime' => date( DATE_RFC3339, $this->get_start_time() ),
				'timeZone' => 'UTC'
			],
			'end'         => [
				'dateTime' => date( DATE_RFC3339, $this->get_end_time() ),
				'timeZone' => 'UTC'
			],
			'attendees'   => [
				[ 'email' => $this->get_contact()->get_email() ],
			],
		];

		if ( $this->get_calendar()->is_google_meet_enabled() ) {

			$event['conferenceData'] = [
				'createRequest' => [
					'requestId'             => $this->get_google_meet_conference_id(),
					'conferenceSolutionKey' => [
						'type' => 'hangoutsMeet'
					],
					'status'                => [
						'statusCode' => 'success'
					]
				]
			];
		}

		return $event;
	}

	/**
	 * Add the appointment in the Google
	 */
	public function add_in_google() {

		if ( ! $this->get_calendar()->is_connected_to_google() ) {
			return false;
		}

		$client  = $this->get_calendar()->get_google_client();
		$service = new Google_Service_Calendar( $client );

		\GroundhoggBookingCalendar\Plugin::$instance->replacements->set_appointment( $this );

		// building the request object
		$event = new Google_Service_Calendar_Event( $this->get_google_event_format() );

		try {
			$event_created = $service->events->insert( $this->get_calendar()->get_remote_google_calendar_id(), $event, [ 'conferenceDataVersion' => 1 ] );

			if ( $event_created->hangoutLink ) {
				$this->add_meta( 'google_meet_url', $event_created->hangoutLink );
			}
		} catch ( \Google\Service\Exception $e ) {

			return false;
		}

		return true;
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

		\GroundhoggBookingCalendar\Plugin::$instance->replacements->set_appointment( $this );

		$event = new Google_Service_Calendar_Event( $this->get_google_event_format() );

		try {
			$updatedEvent = $service->events->update( $this->get_calendar()->get_remote_google_calendar_id(), $this->g_uuid, $event, [] );
		} catch ( \Google\Service\Exception $exception ) {

			if ( $exception->getCode() === 404 ) {
				return $this->add_in_google();
			}

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
			$service->events->delete( $this->get_calendar()->get_remote_google_calendar_id(), $this->g_uuid );
		} catch ( Exception $e ) {
		}
	}

	/**
	 * Conflicts if the start and end period intersect with the given time range
	 *
	 * @param $start \DateTime
	 * @param $end   \DateTime
	 *
	 * @return bool
	 */
	public function conflicts( $start, $end ) {

		$start = $start->getTimestamp();
		$end   = $end->getTimestamp();

		return
			// given start is in between start and end
			( $this->start_time <= $start && $this->end_time > $start ) ||
			// given end is in between start and end
			( $this->start_time <= $end && $this->end_time > $end ) ||
			// the given start and end time are within the slot
			( $this->start_time >= $start && $this->end_time <= $end ) ||
			// the slot is within the given time
			( $this->start_time <= $start && $this->end_time >= $end );
	}

	/**
	 * Get a the link to add the appointment to a Google calendar
	 *
	 * @return string
	 */
	public function get_add_to_google_link() {

		$start_formatted = date( 'Ymd\THis\Z', $this->get_start_time() );
		$end_formatted   = date( 'Ymd\THis\Z', $this->get_end_time() );

		return add_query_arg( urlencode_deep( [
			'action'   => 'TEMPLATE',
			'text'     => $this->get_name(),
			'details'  => wp_strip_all_tags( $this->get_google_description() ),
			'location' => $this->get_calendar()->is_zoom_enabled() ? $this->get_meta( 'zoom_join_url' ) : $this->get_location(),
			'dates'    => $start_formatted . '/' . $end_formatted
		] ), 'https://www.google.com/calendar/render' );
	}

	/**
	 * Get an ICS file
	 *
	 * @throws Exception
	 * @return ICS
	 */
	public function get_ics_file() {

		$ics = new ICS( [
			'location'    => $this->get_calendar()->is_zoom_enabled() ? $this->get_meta( 'zoom_join_url' ) : $this->get_location(),
			'description' => str_replace( "\n", "\\n", wp_strip_all_tags( $this->get_details() ) ),
			'HTML'        => minify_html( $this->get_google_description() ),
			'summary'     => $this->get_name(),
			'dtstart'     => Ymd_His( $this->get_start_time() ),
			'dtend'       => Ymd_His( $this->get_end_time() ),
			'url'         => $this->reschedule_link(),
		] );

		return $ics;
	}

	/**
	 * Download the ICS file
	 *
	 * @return string
	 */
	public function get_ics_link() {
		return $this->manage_link( 'ics' );
	}

}
