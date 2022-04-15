<?php

namespace GroundhoggBookingCalendar\Classes;

use Exception;
use Groundhogg\Contact;
use WP_Error;
use Groundhogg\Plugin;
use Google_Service_Calendar;
use Groundhogg\Base_Object_With_Meta;
use GroundhoggBookingCalendar\DB\Appointments;
use function Groundhogg\array_map_keys;
use function Groundhogg\array_map_to_class;
use function Groundhogg\array_map_to_method;
use function Groundhogg\convert_to_local_time;
use function Groundhogg\get_current_ip_address;
use function Groundhogg\get_date_time_format;
use function Groundhogg\id_list_to_class;
use function Groundhogg\utils;
use function Groundhogg\get_db;
use function Groundhogg\get_array_var;
use function Groundhogg\do_replacements;
use function Groundhogg\isset_not_empty;
use function Groundhogg\Ymd;
use function Groundhogg\Ymd_His;
use function GroundhoggBookingCalendar\get_default_availability;
use function GroundhoggBookingCalendar\get_time_format;
use function GroundhoggBookingCalendar\validate_calendar_slug;
use function GroundhoggBookingCalendar\zoom;
use function GroundhoggBookingCalendar\in_between;
use function GroundhoggBookingCalendar\better_human_readable_duration;

class Calendar extends Base_Object_With_Meta {
	protected function get_meta_db() {
		return Plugin::$instance->dbs->get_db( 'calendarmeta' );
	}

	protected function post_setup() {
		// TODO: Implement post_setup() method.
	}

	protected function get_db() {
		return Plugin::$instance->dbs->get_db( 'calendars' );
	}

	protected function get_object_type() {
		return 'calendar';
	}

	public function get_id() {
		return absint( $this->ID );
	}

	public function get_user_id() {
		return absint( $this->user_id );
	}

	public function get_name() {
		return $this->name;
	}

	public function get_slug() {
		return $this->slug;
	}

	public function get_description() {
		return $this->description;
	}

	/**
	 * @return bool
	 */
	public function is_connected_to_google() {
		return $this->get_google_connection_id() && $this->get_local_google_calendar_id();
	}

	/**
	 * @return \Google_Client|WP_Error
	 */
	public function get_google_client() {
		return $this->get_google_connection()->get_client();
	}

	/**
	 * @deprecated
	 * @return bool|mixed|WP_Error
	 */
	public function get_access_token() {
		return $this->get_google_connection()->get_client()->getAccessToken();
	}

	/**
	 * The id of the calendar of which appointments are added to
	 *
	 * @return int
	 */
	public function get_local_google_calendar_id() {
		return $this->get_meta( 'google_calendar_id', true );
	}

	/**
	 * The id of the calendar of which appointments are added to
	 *
	 * @return string
	 */
	public function get_remote_google_calendar_id() {

		$cal = new Google_Calendar( $this->get_local_google_calendar_id() );

		return $cal->get_remote_google_id();

	}

	/**
	 * List of all connected google calendars
	 *
	 * @return array|mixed
	 */
	public function get_google_calendar_list() {
		$list = $this->get_meta( 'google_calendar_list', true ) ?: [];

		if ( ! in_array( $this->get_local_google_calendar_id(), $list ) ) {
			$list[] = $this->get_local_google_calendar_id();
		}

		return $list;
	}

	/**
	 * @return mixed
	 */
	public function show_in_12_hour() {
		return $this->get_meta( 'time_12hour', true );
	}

	public function get_start_time() {
		return $this->get_meta( 'start_time', true );
	}

	public function get_end_time() {
		return $this->get_meta( 'end_time', true );
	}

	/**
	 * @return
	 */
	public function get_future_appointments() {
		global $wpdb;
		$table_name = get_db( 'appointments' )->get_table_name();
		$result     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE start_time >= %d AND calendar_id = %d", time(), $this->get_id() ) );
		$appts      = [];
		foreach ( $result as $appointment ) {
			$appts[] = new Appointment( absint( $appointment->ID ) );
		}

		return $appts;

	}

	/**
	 * Generate a slug based on the calendar name
	 */
	public function generate_slug() {

		$slug = validate_calendar_slug( $this->name, $this->get_id() );

		$this->update( [
			'slug' => $slug
		] );
	}

	/**
	 * @return Appointment[]
	 */
	public function get_past_appointments() {
		global $wpdb;

		$table_name = get_db( 'appointments' )->get_table_name();
		$result     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE start_time <= %d AND calendar_id = %d", time(), $this->get_id() ) );

		$appts = [];
		foreach ( $result as $appointment ) {
			$appts[] = new Appointment( absint( $appointment->ID ) );
		}

		return $appts;
	}

	/**
	 * @param $a int
	 * @param $b int
	 *
	 * @return Appointment[]
	 */
	public function get_appointments_in_range( $a, $b ) {
		global $wpdb;

		$table_name = get_db( 'appointments' )->get_table_name();

		$results = wp_parse_id_list( wp_list_pluck( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE (start_time BETWEEN %d AND %d) OR (end_time BETWEEN %d AND %d)",
			$a, $b, $a, $b ) ), 'ID' ) );
		$appts   = [];
		foreach ( $results as $appointment_id ) {
			$appts[] = new Appointment( $appointment_id );
		}

		return $appts;
	}

	/**
	 * @return Appointment[]
	 */
	public function get_all_appointments() {
		$ids = wp_parse_id_list( wp_list_pluck( get_db( 'appointments' )->query( [ 'calendar_id' => $this->get_id() ] ), 'ID' ) );

		/**
		 * Ids to appointments
		 */
		return id_list_to_class( $ids, Appointment::class );
	}

	public function get_rules() {
		return $this->get_meta( 'rules' ) ?: get_default_availability();
	}

	/**
	 * @return array|mixed
	 */
	public function get_available_periods() {
		$rules = $this->get_rules();

		$periods = [];

		if ( ! empty( $rules ) ) {

			foreach ( $rules as $rule ) {

				if ( isset_not_empty( $periods, $rule['day'] ) ) {
					$periods[ $rule['day'] ][] = [ $rule['start'], $rule['end'] ];
				} else {
					$periods[ $rule['day'] ] = [ [ $rule['start'], $rule['end'] ] ];
				}
			}
		}

		if ( empty( $periods ) ) {

			// Monday is 1 and Sunday is 7
			$periods = wp_parse_args( $periods, [
				'monday'    => [ [ '09:00', '17:00' ] ],
				'tuesday'   => [ [ '09:00', '17:00' ] ],  //, ['08:00','10:00'] ,['15:00','17:00']
				'wednesday' => [ [ '09:00', '17:00' ] ],
				'thursday'  => [ [ '09:00', '17:00' ] ],
				'friday'    => [ [ '09:00', '17:00' ] ],
				'saturday'  => [ [ '09:00', '17:00' ] ],
				'sunday'    => [ [ '09:00', '17:00' ] ],
			] );
		}

		return $periods;
	}

	public function has_linked_form() {
		return (bool) $this->get_linked_form();
	}

	public function get_linked_form() {
		return absint( $this->get_meta( 'override_form_id' ) );
	}

	/**
	 * Gets the appointment lengths as an array [ hours, minutes ]
	 *
	 * @param $as_int bool
	 *
	 * @return array|int
	 */
	public function get_appointment_length( $as_int = false ) {

		$parts = [
			'hours'   => absint( $this->get_meta( 'slot_hour' ) ),
			'minutes' => absint( $this->get_meta( 'slot_minute' ) ),
			'buffer'  => absint( $this->get_meta( 'buffer_time' ) )
		];

		if ( $as_int ) {
			return ( $parts['hours'] * HOUR_IN_SECONDS ) + ( $parts['minutes'] * MINUTE_IN_SECONDS );
		}

		return $parts;
	}

	/**
	 * Ge the formatted appointment length
	 *
	 * @return false|string
	 */
	public function get_appointment_length_formatted() {
		$length = $this->get_appointment_length( true );
		$string = date( 'H:i:s', $length );

		return better_human_readable_duration( $string );
	}

	/**
	 * @throws \Exception
	 * @return \DateInterval
	 */
	public function get_appointment_interval() {
		$atts = $this->get_appointment_length();

		if ( absint( $atts['hours'] ) === 0 && absint( $atts['minutes'] ) === 0 ) {
			$atts['hours']   = 1;
			$atts['minutes'] = 0;
		}

		$str = sprintf( 'PT%1$dH%2$dM', $atts['hours'], $atts['minutes'] );

		return new \DateInterval( $str );
	}

	/**
	 * @throws \Exception
	 * @return \DateInterval
	 */
	public function get_buffer_interval() {
		$atts = $this->get_appointment_length();
		$str  = 'PT' . $atts['buffer'] . 'M';

		return new \DateInterval( $str );
	}

	/**
	 * Get the maximum date that can be booked.
	 *
	 * @throws Exception
	 *
	 * @param bool $as_string
	 *
	 * @return \DateTime|string
	 */
	public function get_max_booking_period( $as_string = true ) {
		$num  = $this->get_meta( 'max_booking_period_count' );
		$type = $this->get_meta( 'max_booking_period_type' );

		if ( ! $num || ! $type ) {
			$num  = 1;
			$type = 'month';
		}

		$dateTime = new \DateTime( 'now', $this->get_timezone() );
		$dateTime->modify( "+{$num} {$type}" );

		if ( $as_string ) {
			return $dateTime->format( 'Y-m-d' );
		}

		return $dateTime;
	}

	/**
	 * @throws Exception
	 *
	 * @param bool $as_string
	 *
	 * @return \DateTime|string
	 */
	public function get_min_booking_period( $as_string = true ) {
		$num  = $this->get_meta( 'min_booking_period_count' );
		$type = $this->get_meta( 'min_booking_period_type' );

		if ( ! $num || ! $type ) {
			$num  = 0;
			$type = 'days';
		}

		$dateTime = new \DateTime( 'now', $this->get_timezone() );
		$dateTime->modify( "+{$num} {$type}" );

		if ( $as_string ) {
			return $dateTime->format( 'Y-m-d' );
		}

		return $dateTime;
	}

	protected $google_appointments;

	/**
	 *
	 * Fetch all the appointments between start and end-time from google calendar.
	 *
	 * @param $min_time
	 * @param $max_time
	 *
	 * @return array
	 */
	protected function get_google_appointments( $min_time, $max_time ) {

		if ( ! $this->is_connected_to_google() ) {
			return [];
		}

		$client = $this->get_google_client();

		$google_min          = date( 'c', $min_time );
		$google_max          = date( 'c', $max_time );
		$google_appointments = [];
		$service             = new Google_Service_Calendar( $client );

		// loop through calendars which are being used for availability
		foreach ( $this->get_google_calendar_list() as $calendar_id ) {

			try {
				$optParams = array(
//							'orderBy'      => 'startTime',
					'singleEvents' => true,
					'timeMin'      => $google_min,
					'timeMax'      => $google_max
				);

				$results = $service->events->listEvents( $calendar_id, $optParams );
				$events  = $results->getItems();

				if ( empty( $events ) ) {
					continue;
				}

				foreach ( $events as $event ) {

					$google_start = $event->start->dateTime;
					$google_end   = $event->end->dateTime;
					/**
					 * event contains start and end time thus it is appointment
					 */
					if ( ! empty( $google_start ) && ! empty( $google_end ) ) {

						if ( strpos( $google_start, 'Z' ) ) {

							$google_start = str_replace( 'Z', '', $google_start );
							$google_end   = str_replace( 'Z', '', $google_end );

							$google_appointments[] = array(
								'display' => $event->getSummary(),
								'start'   => utils()->date_time->convert_to_utc_0( strtotime( '+1 seconds', strtotime( date( $google_start ) ) ) ),
								'end'     => utils()->date_time->convert_to_utc_0( strtotime( date( $google_end ) ) )
							);

						} else {

							$google_appointments[] = array(
								'display' => $event->getSummary(),
								'start'   => strtotime( '+1 seconds', utils()->date_time->convert_to_utc_0( strtotime( date( $google_start ) ) ) ),
								'end'     => utils()->date_time->convert_to_utc_0( strtotime( date( $google_end ) ) )
							);
						}

					} else {
						/**
						 * Event does not contain start and end date time thus its all day event
						 */

						if ( $event->start->dateTime == null ) {
							$google_start = $event->start->date;
						}

						if ( $event->end->dateTime == null ) {
							$google_end = $event->end->date;
						}

						$google_appointments[] = array(
							'display' => $event->getSummary(),
							'start'   => strtotime( '+1 seconds', utils()->date_time->convert_to_utc_0( strtotime( date( $google_start ) ) ) ),
							'end'     => utils()->date_time->convert_to_utc_0( strtotime( date( $google_end ) ) )
						);
					}
				}
			} catch ( Exception $e ) {
				// catch if the calendar does not exist in google calendar
				return [];
			}
		}

		return $google_appointments;
	}

	/**
	 * Returns day number based on day name
	 *
	 * @param $day string
	 *
	 * @return int
	 */
	public function get_day_number( $day ) {
		$days = [
			'monday'    => 1,
			'tuesday'   => 2,
			'wednesday' => 3,
			'thursday'  => 4,
			'friday'    => 5,
			'saturday'  => 6,
			'sunday'    => 0
		];

		return $days[ $day ];
	}

	/**
	 * Whether an admin notification is enabled or not.
	 *
	 * @param $which
	 *
	 * @return bool
	 */
	public function is_admin_notification_enabled( $which ) {
		$notifications = $this->get_meta( 'enabled_admin_notifications' );

		// Default to true if these have not been specifically configured.
		if ( ! $notifications ) {
			return true;
		}

		return (bool) get_array_var( $notifications, $which );
	}

	/**
	 * Whether SMS notifications are enabled or not.
	 *
	 * @return bool
	 */
	public function are_sms_notifications_enabled() {
		return (bool) $this->get_meta( 'enable_sms_notifications' );
	}


	/**
	 * Get the set of notification emails.
	 *
	 * @param string $which which email to retrieve, otherwise get ALL emails
	 *
	 * @return array|int
	 */
	public function get_email_notification( $which = false ) {
		$emails = $this->get_meta( 'email_notifications' );

		if ( ! $which ) {
			return $emails;
		}

		return get_array_var( $emails, $which );
	}

	/**
	 * Get the set of notification SMS.
	 *
	 * @param string $which
	 *
	 * @return mixed|int
	 */
	public function get_sms_notification( $which = false ) {
		$sms = $this->get_meta( 'sms_notifications' );

		if ( ! $which ) {
			return $sms;
		}

		return get_array_var( $sms, $which );
	}

	/**
	 * Get the list of reminder emails...
	 *
	 * @return array
	 */
	public function get_email_reminders() {
		$reminders = $this->get_meta( 'email_reminders' );

		if ( ! is_array( $reminders ) ) {
			$reminders = []; // Todo Add defaults?
		}

		foreach ( $reminders as &$reminder ) {
			$reminder['number']   = absint( $reminder['number'] );
			$reminder['email_id'] = absint( $reminder['email_id'] );
		}

		return $reminders;
	}

	/**
	 * Get the list of reminder emails...
	 *
	 * @return array
	 */
	public function get_sms_reminders() {
		$reminders = $this->get_meta( 'sms_reminders' );

		if ( ! is_array( $reminders ) ) {
			$reminders = []; // Todo Add defaults?
		}

		foreach ( $reminders as &$reminder ) {
			$reminder['number'] = absint( $reminder['number'] );
			$reminder['sms_id'] = absint( $reminder['sms_id'] );
		}

		return $reminders;
	}

	/**
	 * Add a new appointment to this calendar.
	 *
	 * @param $contact Contact
	 * @param $args    array list of args for the appointment
	 *
	 * @return Appointment|false
	 */
	public function schedule_appointment( $contact, $args ) {

		$args = wp_parse_args( $args, [
			'contact_id'  => $contact->get_id(),
			'calendar_id' => $this->get_id(),
			'status'      => 'scheduled',
			'start_time'  => time(),
			'end_time'    => false,
			'notes'       => '',
		] );

		if ( ! $args['end_time'] ) {
			$end_time = new \DateTime();
			$end_time->setTimestamp( $args['start_time'] );
			$end_time->add( $this->get_appointment_interval() );
			$args['end_time'] = $end_time->getTimestamp();
		}

		get_db('appointments')->

		$args = apply_filters( 'groundhogg/calendar/schedule_appointment', $args, $this );

		$appointment = new Appointment( $args );

		if ( ! $appointment->exists() ) {
			return false;
		}

		$appointment->update_meta( 'notes', $args['notes'] );
		$appointment->schedule();

		do_action( 'groundhogg/calendar/schedule_appointment/after', $this, $appointment );

		return $appointment;

	}

	/**
	 * Set the Google account ID
	 *
	 * @param $id
	 *
	 * @return bool|mixed
	 */
	public function set_google_connection_id( $id ) {
		return $this->update_meta( 'google_connection_id', $id );
	}


	/**
	 * Set the Google account ID
	 *
	 * @param $id
	 *
	 * @return bool|mixed
	 */
	public function set_google_calendar_id( $id ) {
		return $this->update_meta( 'google_calendar_id', $id );
	}


	/**
	 * Get the Google Account ID
	 *
	 * @return string
	 */
	public function get_google_connection_id() {
		return $this->get_meta( 'google_connection_id' );
	}

	/**
	 * @var Google_Connection
	 */
	protected $google_connection;

	/**
	 * @return Google_Connection
	 */
	public function get_google_connection() {
		if ( ! $this->google_connection ) {
			$this->google_connection = new Google_Connection( $this->get_google_connection_id() );
		}

		return $this->google_connection;
	}

	/**
	 * Returns the evalue of google meet enabled checkbox.
	 *
	 * @return bool
	 */
	public function is_google_meet_enabled() {
		return (bool) $this->get_meta( 'google_meet_enable', true );
	}

	/**
	 * Return value of zoom enable checkbox.
	 *
	 * @return bool
	 */
	public function is_zoom_enabled() {
		return (bool) $this->get_meta( 'zoom_account_id', true );
	}

	/**
	 * fetch access_token for the zoom
	 *
	 * @return WP_Error|string
	 */
	public function get_zoom_access_token( $refresh_if_expired = false ) {

		$account_id = $this->get_zoom_account_id();
		$token      = zoom()->get_access_token( $account_id );

		if ( ! $token || ! $account_id ) {
			return new WP_Error( 'error', 'no token' );
		}

		if ( $refresh_if_expired && zoom()->is_token_expired( $account_id ) ) {
			$account_id = zoom()->refresh_connection( $account_id );

			if ( is_wp_error( $account_id ) ) {
				return $account_id;
			}

			$token = zoom()->get_access_token( $account_id );
		}

		return $token;
	}

	/**
	 * Set the zoom access token
	 *
	 * @param string $id
	 */
	public function set_zoom_account_id( $id ) {
		$this->update_meta( 'zoom_account_id', $id );
	}

	/**
	 * Set the soom access token
	 *
	 * @param string $id
	 */
	public function get_zoom_account_id() {
		return $this->get_meta( 'zoom_account_id' );
	}

	/**
	 * Merged list of synced events and all appointments
	 *
	 * @return array
	 */
	public function get_events() {
		$local_appointments = $this->get_all_appointments();

		$google_events = get_db( 'synced_events' )->query( [
			'local_gcalendar_id' => $this->get_google_calendar_list()
		] );

		$google_events = array_map_to_class( $google_events, Synced_Event::class );

		return array_values( array_merge( $google_events, $local_appointments ) );
	}

	/**
	 * Get all appointments from this calendar and also any google events
	 */
	public function get_events_for_full_calendar() {
		return array_map_to_method( $this->get_events(), 'get_for_full_calendar' );
	}

	/**
	 * Get the business hours in a readable format for Fullcalendar
	 *
	 * @return array
	 */
	public function get_business_hours() {

		$availability = $this->get_meta( 'rules', true ) ?: get_default_availability();

		$business_hours = [];

		foreach ( $availability as $avail ) :
			$business_hours[] = [
				'dow'   => $this->get_day_number( $avail['day'] ),
				'start' => $avail['start'],
				'end'   => $avail['end'],
			];
		endforeach;

		return $business_hours;
	}

	/**
	 * Get the availability for a calendar
	 *
	 * @param $start           string a date
	 * @param $end             string a date
	 * @param $client_timezone string|\DateTimeZone the timezone of the client
	 *
	 * @return array
	 */
	public function get_availability( $start = '', $end = '' ) {

		if ( ! $start ) {
			$start = Ymd();
		}

		if ( ! $end ) {
			$end = $this->get_max_booking_period();
		}

		// Get all the scheduled appointments
		$appointments = $this->get_events();

		$timezone = $this->get_timezone();

		// Where we start
		$dateTime = new \DateTime( $start, $timezone );

		// Set to min bookuing period
		if ( $dateTime < $this->get_min_booking_period( false ) ) {
			$dateTime = $this->get_min_booking_period( false );
		}

		// Max booking period is always the max
		$endTime = min( new \DateTime( $end, $timezone ), $this->get_max_booking_period( false ) );
		$endTime->modify( '+1 day' );

		$aptInterval    = $this->get_appointment_interval();
		$bufferInterval = $this->get_buffer_interval();

		$business_hours = $this->get_business_hours();

		$availability = [];

		while ( $dateTime < $endTime ) { // todo make a condition

			$today = $dateTime->getTimestamp();

			$todays_hours = array_filter( $business_hours, function ( $rule ) use ( $dateTime ) {
				return $rule['dow'] === absint( $dateTime->format( 'w' ) );
			} );

			$todays_slots = [];

			foreach ( $todays_hours as $rule ) {

				$maxDate = clone $dateTime;
				$dateTime->modify( $rule['start'] );
				$maxDate->modify( $rule['end'] );

				while ( $dateTime < $maxDate ) {

					$start = $dateTime->getTimestamp();
					$dateTime->add( $aptInterval );
					$end = $dateTime->getTimestamp();

					$has_conflict = false;

					/**
					 * @var $apt Appointment|Synced_Event
					 */
					foreach ( $appointments as $apt ) {
						// found conflict
						if ( $apt->conflicts( $start, $end ) ) {
							$has_conflict = true;
							break;
						}
					}

					if ( ! $has_conflict && $start > time() ) {
						$todays_slots[] = [
							'start' => $start,
							'month' => $dateTime->format( 'n' ) - 1,
						];
					}

					$dateTime->add( $bufferInterval );
				}

			}

			$max_slots = absint( $this->get_meta( 'busy_slot' ) );

			if ( $max_slots && count( $todays_slots ) > $max_slots ) {
				$seed = $today;
				mt_srand( $seed );
				$todays_slots_keys = array_rand( $todays_slots, $max_slots );
				$todays_slots      = array_map( function ( $k ) use ( $todays_slots ) {
					return $todays_slots[ $k ];
				}, $todays_slots_keys );
			}

			$availability = array_merge( $availability, $todays_slots );

			$dateTime->modify( '+ 1 day' );

		}

		return $availability;
	}

	/**
	 * Get the calendar's timezone
	 *
	 * @return \DateTimeZone
	 */
	private function get_timezone() {
		return new \DateTimeZone( $this->get_meta( 'timezone' ) ?: wp_timezone_string() );
	}

}
