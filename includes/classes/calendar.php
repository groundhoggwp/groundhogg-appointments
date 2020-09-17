<?php

namespace GroundhoggBookingCalendar\Classes;

use Exception;
use Google_Service_Calendar;
use Google_Service_Calendar_Calendar;
use Groundhogg\Base_Object_With_Meta;
use function GroundhoggBookingCalendar\get_tz_db_name;
use WP_Error;
use function Groundhogg\get_array_var;
use function Groundhogg\get_db;
use function Groundhogg\isset_not_empty;
use Groundhogg\Plugin;
use function GroundhoggBookingCalendar\better_human_readable_duration;
use function GroundhoggBookingCalendar\convert_to_client_timezone;
use function GroundhoggBookingCalendar\in_between;
use function GroundhoggBookingCalendar\in_between_inclusive;
use GroundhoggBookingCalendar\DB\Appointments;
use function GroundhoggBookingCalendar\send_reminder_notification;
use function GuzzleHttp\Psr7\str;

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

	public function get_description() {
		return $this->description;
	}

	public function google_enabled() {
		return $this->get_access_token() && $this->get_google_calendar_id();
	}

	public function get_access_token() {
		return $this->get_meta( 'access_token', true );
	}

	public function get_google_calendar_id() {
		return $this->get_meta( 'google_calendar_id', true );
	}

	public function get_google_calendar_list() {
		return $this->get_meta( 'google_calendar_list', true );
	}

	/**
	 * Create new google calendar inside google
	 */
	public function add_in_google() {
		$access_token = $this->get_access_token();

		if ( $access_token ) {
			$google_calendar = new Google_Service_Calendar_Calendar();
			// fetch calendar name from DB
			$client = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->get_google_client_from_access_token( $this->get_id() );

			$google_calendar->setSummary( $this->get_name() );

			if ( get_tz_db_name() ) {
				$google_calendar->setTimeZone( get_tz_db_name() );
			}

			$service         = new Google_Service_Calendar( $client );
			$createdCalendar = $service->calendars->insert( $google_calendar );
			$this->update_meta( 'google_calendar_id', sanitize_text_field( $createdCalendar->getId() ) );

			$appointments = get_db( 'appointments' )->query( [ 'calendar_id' => $this->get_id() ] );

			foreach ( $appointments as $appo ) {
				$appointment = new Appointment( $appo->ID );
				$appointment->add_in_google();
			}
		}
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
		$ids = wp_parse_id_list( wp_list_pluck( Plugin::$instance->dbs->get_db( 'appointments' )->query( [ 'calendar_id' => $this->get_id() ] ), 'ID' ) );

		$appts = [];

		foreach ( $ids as $id ) {

			$appts[] = new Appointment( $id );
		}

		return $appts;
	}

	public function get_rules() {
		return $this->get_meta( 'rules' );
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

	/**
	 * @param string $day
	 *
	 * @return bool|mixed
	 */
	public function get_day_available_periods( $day = 'monday' ) {
		$periods = $this->get_available_periods();
		if ( array_key_exists( $day, $periods ) ) {

			return $periods[ $day ];
		} else {
			return false;
		}
	}

	/**
	 * @param int|string $date
	 *
	 * @return array
	 */
	public function get_todays_available_periods( $date = 0 ) {

		if ( ! $date ) {
			$date = 'now';
		}

		if ( is_string( $date ) ) {
			$date = strtotime( $date );
		}

		$today = strtolower( date( 'l', $date ) );

		return $this->get_day_available_periods( $today );
	}

	public function has_linked_form() {
		return (bool) $this->get_linked_form();
	}

	public function get_linked_form() {
		return absint( $this->get_meta( 'override_form_id' ) );
	}

	/**
	 * Get the disabled days.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_dates_no_slots() {
		$start = new \DateTime();
		$end   = $this->get_max_booking_period( false );

		$disabled_dates = [];

		$str = sprintf( 'P%dD', 1 );

		$interval = new \DateInterval( $str );

		while ( $start < $end ) {

			// check with the dates
			$periods = $this->get_todays_available_periods( $start->getTimestamp() );

			if ( ! $periods ) {
				$disabled_dates[] = $start->format( 'Y-m-d' );
			} else {
				//check by checking the time slots
				$slots = $this->get_appointment_slots( $start->format( 'Y-m-d' ) );
				if ( empty( $slots ) ) {
					$disabled_dates[] = $start->format( 'Y-m-d' );
				}
			}

			$start->add( $interval );
		}

		return $disabled_dates;
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
	 * @return \DateInterval
	 * @throws \Exception
	 */
	public function get_appointment_interval() {
		$atts = $this->get_appointment_length();

		if ( absint( $atts['hours'] ) === 0 && absint( $atts['minutes'] ) === 0 ) {
			$atts['hours']   = 1;
			$atts['minutes'] = 0;
		}

		$str = sprintf( 'PT%1$dH%2$dM', $atts['hours'], $atts['minutes'] );

//        $str = 'PT' . $atts[ 'hours' ] . 'H' . $atts[ 'minutes' ] . 'M';
		return new \DateInterval( $str );
	}

	/**
	 * @return \DateInterval
	 * @throws \Exception
	 */
	public function get_buffer_interval() {
		$atts = $this->get_appointment_length();
		$str  = 'PT' . $atts['buffer'] . 'M';

		return new \DateInterval( $str );
	}

	/**
	 * Get the maximum date that can be booked.
	 *
	 * @param bool $as_string
	 *
	 * @return \DateTime|string
	 * @throws Exception
	 */
	public function get_max_booking_period( $as_string = true ) {
		$num  = $this->get_meta( 'max_booking_period_count' );
		$type = $this->get_meta( 'max_booking_period_type' );

		if ( ! $num || ! $type ) {
			$num  = 1;
			$type = 'month';
		}

		$interval = new \DateTime( date( 'Y-m-d H:i:s', strtotime( "+{$num} {$type}" ) ) );

		if ( $as_string ) {
			return $interval->format( 'Y-m-d' );
		}

		return $interval;
	}


	/**
	 * @param bool $as_string
	 *
	 * @return \DateTime|string
	 * @throws Exception
	 */
	public function get_min_booking_period( $as_string = true ) {
		$num  = $this->get_meta( 'min_booking_period_count' );
		$type = $this->get_meta( 'min_booking_period_type' );

		if ( ! $num || ! $type ) {
			$num  = 0;
			$type = 'days';
		}

		$interval = new \DateTime( date( 'Y-m-d H:i:s', strtotime( "+{$num} {$type}" ) ) );

		if ( $as_string ) {
			return $interval->format( 'Y-m-d' );
		}

		return $interval;
	}


	/**
	 * Fetches valid time slots for end using by performing all the cleaning operations.
	 * (Most Important method)
	 *
	 * @param string $date
	 * @param string $timezone
	 *
	 * @return array|false|mixed
	 */
	public function get_appointment_slots( $date = '', $timezone = '' ) {
		$slots = $this->get_all_available_slots( strtotime( $date ) );

		$slots = $this->sort_slots( $slots );

		if ( ! $slots ) {
			return [];
		}


		$slots = $this->validate_appointments( $slots );
		$slots = $this->clean_google_slots( $slots );

		if ( ! current_user_can( 'edit_appointment' ) ) {
			$slots = $this->make_me_look_busy( $slots, strtotime( $date ) );
		}
		$slots = $this->get_slots_name( $slots, $timezone );

		return $slots;
	}

	/**
	 * Adds display title based on the timezone. If timezone is not given then it converts that into local time using local time php function.
	 *
	 * @param $slots
	 * @param $timezone
	 *
	 * @return mixed
	 */
	protected function get_slots_name( $slots, $timezone ) {
		if ( $timezone ) {

			foreach ( $slots as $i => $slot ) {

				$format = $this->show_in_12_hour() ? "h:i A" : "H:i";
				// Show display...
				try {

					$slots[ $i ]['display'] = sprintf( '%s - %s',
						date_i18n( $format, Plugin::$instance->utils->date_time->convert_to_foreign_time( absint( $slot['start'] ), $timezone ) ),
						date_i18n( $format, Plugin::$instance->utils->date_time->convert_to_foreign_time( absint( $slot['end'] ), $timezone ) )
					);
				} catch ( Exception $e ) {
					$slots[ $i ]['display'] = sprintf( '%s - %s',
						date_i18n( $format, Plugin::$instance->utils->date_time->convert_to_local_time( absint( $slot['start'] ) ) ),
						date_i18n( $format, Plugin::$instance->utils->date_time->convert_to_local_time( absint( $slot['end'] ) ) )
					);
				}
			}

		} else {
			foreach ( $slots as $i => $slot ) {

				$format = $this->show_in_12_hour() ? "h:i A" : "H:i";

				// Show display...
				$slots[ $i ]['display'] = sprintf( '%s - %s',
					date_i18n( $format, Plugin::$instance->utils->date_time->convert_to_local_time( absint( $slot['start'] ) ) ),
					date_i18n( $format, Plugin::$instance->utils->date_time->convert_to_local_time( absint( $slot['end'] ) ) )
				);
			}
		}

		return $slots;
	}

	/**
	 * Slots will be returned as
	 *
	 * [
	 *   'start' => (int),
	 *   'end'   => (int),
	 * ]
	 *
	 * e.g.
	 *
	 * [
	 *   'start' => 1234,
	 *   'end'   => 1234,
	 * ]
	 *
	 *
	 * @param int $date (Expected in UTC 0)
	 *
	 * @return array|false on failure
	 */
	protected function get_all_available_slots( $date = 0 ) {
		if ( is_string( $date ) ) {
			$date = strtotime( $date );
		}


		$str_date          = date( 'Y-m-d H:i:s', $date );
		$str_date_no_hours = date( 'Y-m-d', $date );

		$request_date = new \DateTime( $str_date );
		// The actual slots we plan on returning.
		$slots = [];

		$available_periods = $this->get_todays_available_periods( $date );

		if ( ! is_array( $available_periods ) ) {
			return false;
		}

		$base_time = time();

		if ( $this->get_meta( 'min_booking_period_type' ) == 'hours' ) {
			$base_time = $base_time + ( 60 * 60 * intval( $this->get_meta( 'min_booking_period_count' ) ) );
		}

		foreach ( $available_periods as $period ) {

			// 09:00
			$start_time = date( 'H:i:s', strtotime( "{$period[0]}:00" ) );
			$start_time = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( "{$str_date_no_hours} {$start_time}" ) );
			if ( $start_time < $base_time ) {
				$start_time = strtotime( date( 'Y-m-d H:00:00', $base_time ) );
			}

			$start_date = new \DateTime( date( 'Y-m-d H:i:s', $start_time ) );

			// 17:00
			$end_time = date( 'H:i:s', strtotime( "{$period[1]}:00" ) );
			$end_time = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( "{$str_date_no_hours} {$end_time}" ) );
			$end_date = new \DateTime( date( 'Y-m-d H:i:s', $end_time ) );

			if ( $end_date < $request_date ) {
				continue;
			}

			while ( $start_date < $end_date ) {

				$slot_start = $start_date->format( 'U' );

				try {
					$start_date->add( $this->get_appointment_interval() );
				} catch ( \Exception $e ) {
					return false;
				}

				$slot_end = $start_date->format( 'U' );

				if ( $slot_end <= $end_date->format( 'U' ) ) {
					$slots[] = [
						'start' => $slot_start,
						'end'   => $slot_end,
					];
				}

				try {
					$start_date->add( $this->get_buffer_interval() );
				} catch ( \Exception $e ) {
					return false;
				}
			}
		}

		return $slots;
	}

	/**
	 * @param $slots
	 *
	 * @return array
	 */
	protected function validate_against_appointments( $slots ) {
		if ( empty( $slots ) ) {
			return $slots;
		}

		$available_slots = [];

		/**
		 * @var $db Appointments
		 */
		$db = get_db( 'appointments' );

		foreach ( $slots as $slot ) {
			if ( ! $db->appointments_exist_in_range( $slot['start'], $slot['end'], $this->get_id() ) ) {
				$available_slots[] = $slot;
			}
		}

		return $available_slots;
	}

	/**
	 * @return bool
	 */
	public function delete() {
		$status = $this->get_db()->delete( $this->get_id() );

		if ( ! $status ) {
			return $status;
		}

		return true;
	}


	/**
	 *
	 * Fetch all the appointments between start and end-time from google calendar.
	 *
	 * @param $min_time
	 * @param $max_time
	 *
	 * @return array
	 */
	protected function get_google_appointment( $min_time, $max_time ) {
		$access_token       = $this->get_access_token();
		$google_calendar_id = $this->get_google_calendar_id();
		if ( $access_token && $google_calendar_id ) {

			$google_min           = date( 'c', ( $min_time ) );
			$google_max           = date( 'c', ( $max_time ) );
			$google_appointments  = [];
			$client               = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->get_google_client_from_access_token( $this->get_id() );
			$service              = new Google_Service_Calendar( $client );
			$google_calendar_list = $this->get_meta( 'google_calendar_list', true ) ?: [];
			if ( count( $google_calendar_list ) > 0 ) {
				foreach ( $google_calendar_list as $google_cal ) {
					try {
						$google_calendar = $service->calendars->get( $google_cal );
						$optParams       = array(
//							'orderBy'      => 'startTime',
							'singleEvents' => true,
							'timeMin' => $google_min,
							'timeMax' => $google_max
						);
						$results         = $service->events->listEvents( $google_calendar->getId(), $optParams );
						$events          = $results->getItems();


						if ( ! empty( $events ) ) {
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
											'start'   => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( '+1 seconds', strtotime( date( $google_start ) ) ) ),
											'end'     => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( date( $google_end ) ) )
										);

									} else {

										$google_appointments[] = array(
											'display' => $event->getSummary(),
											'start'   => strtotime( '+1 seconds', Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( date( $google_start ) ) ) ),
											'end'     => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( date( $google_end ) ) )
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
										'start'   => strtotime( '+1 seconds', Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( date( $google_start ) ) ) ),
										'end'     => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( date( $google_end ) ) )
									);
								}
							}
						}

					} catch ( Exception $e ) {
						// catch if the calendar does not exist in google calendar
						return [];
					}
				}
			}

			return $google_appointments;
		}

		return [];
	}


	protected $google_appointments;

	/**
	 * Removes google appointment slots from all the appointment slots.
	 *
	 * @param $slots
	 *
	 * @return array
	 */
	protected function clean_google_slots( $slots ) {

		if ( empty( $slots ) ) {
			return $slots;
		}

		if ( ! $this->google_appointments ) {
			$this->google_appointments = $this->get_google_appointment( strtotime( $this->get_min_booking_period() ), strtotime( $this->get_max_booking_period() ) );
		}


		if ( empty( $this->google_appointments ) ) {
			return $slots;
		}

		$clean1 = $this->clean_big_appointment( $slots, $this->google_appointments );
		$clean2 = $this->clean_small_appointment( $clean1, $this->google_appointments );

		return $clean2;
	}


	/**
	 * Fetches valid appointment.
	 *
	 * @param $slots
	 *
	 * @return array
	 */
	protected function validate_appointments( $slots ) {
		/**
		 * @var $db Appointments
		 */
		$db = get_db( 'appointments' );

		$data         = [];
		$appointments = $db->appointments_exist_in_range( absint( $slots[0]['start'] ), absint( $slots [ sizeof( $slots ) - 1 ] ['end'] ), $this->get_id() );

		if ( empty( $appointments ) ) {
			return $slots;
		}

		foreach ( $appointments as $appointment ) {
			$data[] = [
				'start' => strtotime( '+1 seconds', absint( $appointment->start_time ) ),
				'end'   => absint( $appointment->end_time )
			];
		}

		$clean1 = $this->clean_big_appointment( $slots, $data );
		$clean2 = $this->clean_small_appointment( $clean1, $data );

		return $clean2;
	}


	/**
	 * Clean appointments where time slots are smaller then appointment.
	 *
	 * @param $slots
	 * @param $appointments
	 *
	 * @return array
	 */
	protected function clean_big_appointment( $slots, $appointments ) {
		$clean_slots = [];
		foreach ( $slots as $slot ) {
			$is_booked = false;
			foreach ( $appointments as $appointment ) {
				if ( in_between( $slot['start'], $appointment['start'], $appointment['end'] ) || in_between( $slot['end'], $appointment['start'], $appointment['end'] ) ) {
					$is_booked = true;
					break;
				}
			}
			if ( ! $is_booked ) {
				$clean_slots[] = $slot;
			}
		}

		return $clean_slots;
	}

	/**
	 * Clean appointments where time slots are bigger then appointment.
	 *
	 * @param $slots
	 * @param $appointments
	 *
	 * @return array
	 */
	protected function clean_small_appointment( $slots, $appointments ) {
		$clean_slots = [];
		// cleaning where appointments are smaller then slots
		foreach ( $slots as $slot ) {
			$is_booked = false;
			foreach ( $appointments as $appointment ) {
				if ( in_between( $appointment['start'], $slot['start'], $slot['end'] ) ) {
					$is_booked = true;
					break;
				}
			}
			if ( ! $is_booked ) {
				$clean_slots[] = $slot;
			}
		}

		return $clean_slots;
	}


	/**
	 *  Removes duplicate slots from the slots array and sort array to display time slots in ascending order.
	 *
	 * @param $slot
	 *
	 * @return array
	 */
	protected function sort_slots( $slot ) {
		$sort = [];

		if ( empty( $slot ) ) {
			return $slot;
		}

		$slot = array_map( "unserialize", array_unique( array_map( "serialize", $slot ) ) );

		foreach ( $slot as $key => $row ) {
			$sort[ $key ] = $row['start'];
		}
		array_multisort( $sort, SORT_ASC, $slot );

		return $slot;
	}

	/**
	 * Return number of slots based on value entered in meta
	 *
	 * @param $slots array
	 * @param $date int
	 *
	 * @return array
	 */
	protected function make_me_look_busy( $slots, $date ) {
		$no_of_slots = absint( $this->get_meta( 'busy_slot', true ) );
		if ( ! $no_of_slots ) {
			return $slots;
		}

		if ( $no_of_slots >= count( $slots ) ) {
			return $slots;
		}


		$busy_slots = [];
		$this->shuffle_appointments( $slots, $date );
		for ( $i = 0; $i < $no_of_slots; $i ++ ) {
			$busy_slots[] = $slots[ $i ];
		}

		return $this->sort_slots( $busy_slots );
	}


	/**
	 * shuffle array to get random appointment from appointment list.
	 *
	 * @param $items
	 * @param $seed
	 */
	public function shuffle_appointments( &$items, $seed ) {
		@mt_srand( $seed );
		for ( $i = count( $items ) - 1; $i > 0; $i -- ) {
			$j           = @mt_rand( 0, $i );
			$tmp         = $items[ $i ];
			$items[ $i ] = $items[ $j ];
			$items[ $j ] = $tmp;
		}
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
	 * Get the set of notification emails.
	 *
	 * @param string $which which email to retrieve, otherwise get ALL emails
	 *
	 * @return array|int
	 */
	public function get_notification_emails( $which = '' ) {
		$emails = $this->get_meta( 'emails' );

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
	public function get_notification_sms( $which = '' ) {
		$sms = $this->get_meta( 'sms' );

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
	public function get_reminder_emails() {
		$reminders = $this->get_meta( 'reminders' );

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
	public function get_reminder_sms() {
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
	 * @param $args array list of args for the appointment
	 *
	 * @return Appointment|false
	 */
	public function schedule_appointment( $args ) {
		$args = wp_parse_args( $args, [
			'contact_id'  => 0,
			'calendar_id' => $this->get_id(),
			'name'        => $this->get_name(),
			'status'      => 'pending',
			'start_time'  => time(),
			'end_time'    => time() + $this->get_appointment_length( true ),
			'notes'       => ''
		] );

		do_action( 'groundhogg/calendar/schedule_appointment/before', $this, $args );

		$args = apply_filters( 'groundhogg/calendar/schedule_appointment', $args, $this );

		$note = $args['notes'];
		unset( $args['notes'] );
		$appointment = new Appointment( $args );

		$notes = '';
		if ( $appointment->get_calendar()->get_meta( 'default_note' ) ) {
			$notes = $appointment->get_calendar()->get_meta( 'default_note' );
			$notes .= sprintf( "\n\n========== \n\n" );
		}
		$notes .= $note;

		$appointment->update_meta( 'notes', $notes );

		if ( ! $appointment->exists() ) {
			return false;
		}

		$appointment->create_zoom_meeting();

		$appointment->add_in_google();

		$appointment->book();

		do_action( 'groundhogg/calendar/schedule_appointment/after', $this, $appointment );

		do_action( 'groundhogg/calendar/appointment/book', $appointment->get_id(), Reminder::BOOKED );

		if ( $args['status'] === 'approved' ) {
			$appointment->approve();
		}

		if ( $args['status'] === 'cancelled' ) {
			$appointment->cancel();
		}

		return $appointment;

	}

	/**
	 * Return value of zoom enable checkbox.
	 *
	 * @return bool
	 */
	public function is_zoom_enabled() {
		return (bool) $this->get_meta( 'zoom_enable', true );
	}

	/**
	 * Returns the evalue of google meet enabled checkbox.
	 * @return bool
	 */
	public function is_google_meet_enabled()
	{
		return (bool) $this->get_meta( 'google_meet_enable', true );
	}


	/**
	 * fetch access_token for the zoom
	 *
	 * @return WP_Error | string
	 */
	public function get_access_token_zoom() {
		if ( ! $this->is_access_token_zoom() ) {
			return new WP_Error( 'no_token', 'Access token not found!' );;
		}

		// refresh token
		$json = json_decode( $this->get_meta( 'access_token_zoom', true ) );

		if ( ! $json->refresh_token ) {
			return new WP_Error( 'no_refresh', 'Refresh token not found' );
		}

		$response = Plugin::instance()->proxy_service->request( 'authentication/refresh', [
			'token' => $json->refresh_token,
			'slug'  => 'zoom'
		] );

		if ( is_wp_error( $response ) ) {
			$this->delete_meta( 'access_token_zoom' );

			return new WP_Error( 'rest_error', $response->get_error_message() );
		}

		$access_token = get_array_var( $response, 'token' );

		if ( ! $access_token ) {
			$this->delete_meta( 'access_token_zoom' );

			return new WP_Error( 'no_token', __( 'Could not retrieve access token.', 'groundhogg-calendar' ) );
		}

		$access_token_json_encoded = json_encode( $access_token );
		$access_token_json_decoded = json_decode( $access_token_json_encoded );

		if ( $access_token_json_decoded->access_token ) {
			$this->update_meta( 'access_token_zoom', $access_token_json_encoded );

			return $access_token_json_decoded->access_token;
		}
		$this->delete_meta( 'access_token_zoom' );

		return new WP_Error( 'no_token', __( 'Token refresh Failed', 'groundhogg-calendar' ) );
	}

	public function is_access_token_zoom() {
		return (bool) $this->get_meta( 'access_token_zoom', true );
	}


}