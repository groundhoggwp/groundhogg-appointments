<?php

namespace GroundhoggBookingCalendar\Classes;

use Exception;
use Groundhogg\Contact;
use GroundhoggBookingCalendar\Calendar_Sync;
use GroundhoggBookingCalendar\Reminders_And_Notifications;
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
use function Groundhogg\get_time;
use function Groundhogg\id_list_to_class;
use function Groundhogg\managed_page_url;
use function Groundhogg\utils;
use function Groundhogg\get_db;
use function Groundhogg\get_array_var;
use function Groundhogg\do_replacements;
use function Groundhogg\isset_not_empty;
use function Groundhogg\Ymd;
use function Groundhogg\Ymd_His;
use function GroundhoggBookingCalendar\get_default_availability;
use function GroundhoggBookingCalendar\get_time_format;
use function GroundhoggBookingCalendar\get_user_google_connection;
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
	 * Generate a slug based on the calendar name
	 */
	public function generate_slug() {

		$slug = validate_calendar_slug( $this->name, $this->get_id() );

		$this->update( [
			'slug' => $slug
		] );
	}

	/**
	 * The id of the calendar of which appointments are added to
	 *
	 * @deprecated
	 *
	 * @return int
	 */
	public function get_local_google_calendar_id() {
		return $this->get_meta( 'google_calendar_id', true );
	}

	/**
	 * The id of the calendar of which appointments are added to
	 *
	 * @deprecated
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
	 * @deprecated
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
	 * Get the formatted appointment length
	 *
	 * @return false|string
	 */
	public function get_appointment_length_formatted() {
		$length = $this->get_appointment_length( true );
		$string = date( 'H:i:s', $length );

		return better_human_readable_duration( $string );
	}

	/**
	 * Returns the length of the appointment as a DateInterval object
	 *
	 * @throws \Exception
	 * @return \DateInterval
	 */
	public function get_appointment_interval() {
		$parts = $this->get_appointment_length();

		if ( absint( $parts['hours'] ) === 0 && absint( $parts['minutes'] ) === 0 ) {
			$parts['hours']   = 1;
			$parts['minutes'] = 0;
		}

		$minutes = ( $parts['hours'] * 60 ) + $parts['minutes'];

		return \DateInterval::createFromDateString( sprintf( '%d minutes', $minutes ) );
	}

	/**
	 * Returns the buffer time as a DateInterval
	 *
	 * @throws \Exception
	 * @return \DateInterval
	 */
	public function get_buffer_interval() {
		$atts = $this->get_appointment_length();

		return \DateInterval::createFromDateString( sprintf( '%d minutes', $atts['buffer'] ) );
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
	 * Get the minimum date that can be booked
	 *
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

	/**
	 * Returns day number based on day name
	 *
	 * @param $day string
	 *
	 * @return int
	 */
	public function get_day_number( $day ) {
		$days = [
			'sunday'    => 0,
			'monday'    => 1,
			'tuesday'   => 2,
			'wednesday' => 3,
			'thursday'  => 4,
			'friday'    => 5,
			'saturday'  => 6,
		];

		return $days[ $day ];
	}

	/**
	 * Whether SMS notifications are enabled or not.
	 *
	 * @deprecated
	 *
	 * @return bool
	 */
	public function are_sms_notifications_enabled() {
		return (bool) $this->get_meta( 'enable_sms_notifications' );
	}

	/**
	 * Whether a notification is enabled or not.
	 *
	 * @param $which
	 *
	 * @return bool
	 */
	public function is_notification_enabled( $key, $which ) {
		$notifications = $this->get_meta( $key );

		// Default to true if these have not been specifically configured.
		if ( ! $notifications ) {
			return false;
		}

		$notification_config = get_array_var( $notifications, $which );

		return (bool) get_array_var( $notification_config, 'enabled' );
	}

	/**
	 * Get a notification template
	 *
	 * @param string $key
	 * @param string $which
	 * @param array  $defaults
	 *
	 * @return array
	 */
	public function get_notification_template( $key, $which, $defaults = [] ) {
		$notifications = $this->get_meta( $key );

		// Default to true if these have not been specifically configured.
		if ( ! $notifications ) {
			return $defaults;
		}

		$notification_config = get_array_var( $notifications, $which );

		return wp_parse_args( get_array_var( $notification_config, 'template', [] ), $defaults );
	}

	/**
	 * Whether an admin notification is enabled or not.
	 *
	 * @param $which
	 *
	 * @return bool
	 */
	public function is_admin_email_notification_enabled( $which ) {
		return $this->is_notification_enabled( 'admin_notifications', $which );
	}


	/**
	 * Get the template for a given admin notification
	 *
	 * @param string|int $which which email template to use
	 *
	 * @return array
	 */
	public function get_admin_email_notification_template( $which = false ) {
		switch ( $which ) {
			case Reminders_And_Notifications::CANCELLED:
			case Reminders_And_Notifications::SCHEDULED:
			case Reminders_And_Notifications::RESCHEDULED:
				return $this->get_notification_template( 'admin_notifications', $which, [
					'subject' => '',
					'content' => ''
				] );
			default:
			case Appointment_Reminder::ADMIN_REMINDER:
			case Appointment_Reminder::ADMIN_EMAIL:
				return $this->get_meta( 'personalized_email_notification' );
		}
	}

	/**
	 * Get the template for a given admin notification
	 *
	 * @param string|int $which which email template to use
	 *
	 * @return array
	 */
	public function get_contact_email_notification_template( $which = false ) {
		switch ( $which ) {
			case Reminders_And_Notifications::CANCELLED:
			case Reminders_And_Notifications::SCHEDULED:
			case Reminders_And_Notifications::RESCHEDULED:
				return $this->get_notification_template( 'contact_email_notifications', $which, [
					'subject' => '',
					'content' => ''
				] );
			default:
			case Appointment_Reminder::REMINDER:
			case Appointment_Reminder::CONTACT_EMAIL:
				return $this->get_meta( 'personalized_email_reminder' );
		}
	}

	/**
	 * Get the set of notification SMS.
	 *
	 * @deprecated
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
	public function get_reminders( $which ) {

		$reminders_key_map = [
			Appointment_Reminder::CONTACT_EMAIL => 'email_reminders',
			Appointment_Reminder::CONTACT_SMS   => 'sms_reminders',
			Appointment_Reminder::ADMIN_EMAIL   => 'notifications',
			Appointment_Reminder::ADMIN_SMS     => 'sms_notifications',
		];

		return $this->get_meta( $reminders_key_map[ $which ] );
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
			'owner_id'    => $this->get_user_id(),
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

		$args = apply_filters( 'groundhogg/calendar/schedule_appointment', $args, $this );

		$appointment = new Appointment( $args );

		if ( ! $appointment->exists() ) {
			return false;
		}

		$appointment->update_meta( 'notes', $args['notes'] );

		$location      = '';
		$location_type = $this->get_meta( 'location_type' );

		switch ( $location_type ) {
			case 'zoom':
			case 'google_meet':
				$location = $location_type;
				break;
			case 'address':
				$location = $this->get_meta( 'location_address' );
				break;
			case 'call_you':
				$location = $this->get_meta( 'location_phone' );
				break;
			case 'custom':
				$location = $this->get_meta( 'location_custom' );
				break;
		}

		$appointment->update_meta( 'location', $location );

		$appointment->schedule();

		do_action( 'groundhogg/calendar/schedule_appointment/after', $this, $appointment );

		return $appointment;

	}

	/**
	 * Merged list of synced events and all appointments
	 *
	 * @return Appointment[]
	 */
	public function get_scheduled_events( $from, $to ) {

		$local_appointments = get_db( 'appointments' )->query( [
			// compare against owner ID if assigned to more than 1 calendar
			// rather than the calendar ID
			'owner_id' => $this->get_user_id(),
			'after'    => get_time( $from ),
			'before'   => get_time( $to ),
			'status'   => 'scheduled'
		] );

		array_map_to_class( $local_appointments, Appointment::class );

		$google_events = [];

		$google_connection = get_user_google_connection( $this->get_user_id() );

		if ( $google_connection && is_a( $google_connection, Google_Connection::class ) ) {
			$google_events = $google_connection->get_cached_events( $from, $to );
		}

		$events = array_values( array_merge( $google_events, $local_appointments ) );

		usort( $events, function ( $a, $b ) {
			return $a->get_start_date() <=> $b->get_start_date();
		} );

		return $events;
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
	 * @var array[]
	 */
	protected $conflicts = [];
	protected $events_per_day = [];

	/**
	 * Whether a time is in the list
	 *
	 * @param \DateTime $date
	 *
	 * @return bool
	 */
	public function conflict_exists( \DateTime $date ) {
		return key_exists( $date->getTimestamp(), $this->conflicts );
	}

	/**
	 * Retrieve the conflicting appointment
	 *
	 * @param \DateTime $date
	 *
	 * @return array
	 */
	public function get_conflict( \DateTime $date ) {
		return $this->conflicts[ $date->getTimestamp() ];
	}


	/**
	 * The number of scheduled events on a given day
	 *
	 * @param \DateTime $date
	 *
	 * @return int
	 */
	public function get_num_events( \DateTime $date ) {
		return isset( $this->events_per_day[ $date->format('Y-m-d') ] ) ? $this->events_per_day[ $date->format('Y-m-d') ] : 0;
	}

	/**
	 * Prepare the conflict times
	 *
	 * @param $start
	 * @param $end
	 *
	 * @return void
	 */
	public function prepare_conflicts( $start, $end ) {

		// Get all the scheduled appointments
		// Ordered datetime ascending
		$appointments = $this->get_scheduled_events( $start, $end );
		$_5min        = \DateInterval::createFromDateString( '5 minutes' );

		foreach ( $appointments as $appointment ) {
			$date = $appointment->get_start_date();
			$end  = $appointment->get_end_date();
			$date->setTimezone( $this->get_timezone() );

			if ( method_exists( $appointment, 'get_calendar_id' ) && $appointment->get_calendar_id() === $this->get_id() ){
				$Ymd = $date->format( 'Y-m-d' );

				if ( key_exists( $Ymd, $this->events_per_day ) ){
					$this->events_per_day[$Ymd]++;
				} else {
					$this->events_per_day[$Ymd] = 1;
				}
			}

			$this->conflicts[ $date->getTimestamp() ] = [
				'when' => 'start',
				'end'  => $end->getTimestamp()
			];

			$date->add( $_5min );

			while ( $date < $end ) {
				$this->conflicts[ $date->getTimestamp() ] = [
					'when' => 'inner',
					'end'  => $end->getTimestamp()
				];
				$date->add( $_5min );
			}

			$this->conflicts[ $date->getTimestamp() ] = [
				'when' => 'end',
				'end'  => $end->getTimestamp()
			];
		}
	}

	/**
	 * Get the availability for a calendar
	 *
	 * Takes into account scheduled appointments as well as synced appointments
	 * and will exclude conflicting slots from the availability.
	 *
	 * @throws Exception
	 *
	 * @param $end             string a date
	 * @param $start           string a date
	 *
	 * @return array[]
	 */
	public function get_availability( $start = '', $end = '' ) {

		if ( ! $start ) {
			$start = Ymd();
		}

		if ( ! $end ) {
			$end = $this->get_max_booking_period();
		}

		$this->prepare_conflicts( $start, $end );

		$timezone = $this->get_timezone();

		// Where we start
		$dateTime = new \DateTime( $start, $timezone );

		// Set to min booking period
		if ( $dateTime < $this->get_min_booking_period( false ) ) {
			$dateTime = $this->get_min_booking_period( false );
		}

		// Max booking period is always the max
		$endTime = min( new \DateTime( $end, $timezone ), $this->get_max_booking_period( false ) );
		$endTime->modify( '+1 day' );

		$aptInterval    = $this->get_appointment_interval();
		$bufferInterval = $this->get_buffer_interval();

		$buffA = new \DateTime( 'now' );
		$buffB = clone $buffA;
		$buffB->add( $bufferInterval );

		$bufferInSeconds = $buffB->getTimestamp() - $buffA->getTimestamp();

		$business_hours = $this->get_business_hours();

		$availability = [];
		$checked      = [];

		$limit_slots    = (bool) $this->get_meta( 'limit_slots' );
		$max_slots      = absint( $this->get_meta( 'busy_slot' ) );
		$limit_bookings = (bool) $this->get_meta( 'limit_bookings' );
		$max_bookings   = absint( $this->get_meta( 'max_bookings' ) );

		while ( $dateTime < $endTime ) { // todo make a condition

			$today = $dateTime->getTimestamp();

			$todays_rules = array_filter( $business_hours, function ( $rule ) use ( $dateTime ) {
				return $rule['dow'] === absint( $dateTime->format( 'w' ) );
			} );

			// No slots today!
			if ( empty( $todays_rules ) || ( $limit_bookings && $this->get_num_events( $dateTime ) >= $max_bookings ) ) {
				$dateTime->modify( 'tomorrow' );
				continue;
			}

			$todays_slots = [];

			foreach ( $todays_rules as $rule ) {

				$maxDate = clone $dateTime;
				$dateTime->modify( $rule['start'] );
				$maxDate->modify( $rule['end'] );

				while ( $dateTime < $maxDate ) {

					$start = clone $dateTime;
					$dateTime->add( $aptInterval );
					$end = clone $dateTime;

					if ( $end > $maxDate ) {
						continue;
					}

					// We already visited this potential slot, next!
					if ( key_exists( $start->getTimestamp(), $checked ) ) {
						continue;
					}

					$checked[ $start->getTimestamp() ] = true;

					// Start conflicts
					if ( $this->conflict_exists( $start ) ) {
						$conflict = $this->get_conflict( $start );

						switch ( $conflict['when'] ) {
							case 'start':
							case 'inner':
								$dateTime->setTimestamp( $conflict['end'] );
								$dateTime->add( $bufferInterval );
								continue 2;
							case 'end':

								if ( $bufferInSeconds === 0 ) {
									break;
								}

								$dateTime->add( $bufferInterval );
								continue 2;
						}
					}

					// End conflicts
					if ( $this->conflict_exists( $end ) ) {
						$conflict = $this->get_conflict( $end );

						switch ( $conflict['when'] ) {
							case 'end':
							case 'inner':
								$dateTime->setTimestamp( $conflict['end'] );
								$dateTime->add( $bufferInterval );
								continue 2;
							case 'start':

								if ( $bufferInSeconds === 0 ) {
									break;
								}

								$dateTime->setTimestamp( $conflict['end'] );
								$dateTime->add( $bufferInterval );
								continue 2;
						}
					}

					// Only one slot can exist at this time stamp
					$todays_slots[ $start->getTimestamp() ] = [
						'start' => $start->getTimestamp(),
						'month' => $dateTime->format( 'n' ) - 1,
					];

				}

			}

			$todays_slots = array_values( $todays_slots );

			if ( $limit_slots && count( $todays_slots ) > $max_slots ) {
				$seed = $today;
				mt_srand( $seed );
				$todays_slots_keys = array_rand( $todays_slots, $max_slots );
				$todays_slots      = array_map( function ( $k ) use ( $todays_slots ) {
					return $todays_slots[ $k ];
				}, $todays_slots_keys );
			}

			$availability = array_merge( $availability, $todays_slots );

			$dateTime->modify( 'tomorrow' );

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

	public function get_link() {
		return managed_page_url( sprintf( 'calendar/%s/', $this->slug ) );
	}

	public function get_as_array() {
		return array_merge( parent::get_as_array(), [
			'link' => $this->get_link()
		] );
	}

}
