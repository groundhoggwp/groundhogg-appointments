<?php

namespace GroundhoggBookingCalendar\Classes;

use Exception;
use WP_Error;
use Groundhogg\Plugin;
use Google_Service_Calendar;
use Groundhogg\Base_Object_With_Meta;
use GroundhoggBookingCalendar\DB\Appointments;
use function Groundhogg\array_map_keys;
use function Groundhogg\convert_to_local_time;
use function Groundhogg\utils;
use function Groundhogg\get_db;
use function Groundhogg\get_array_var;
use function Groundhogg\do_replacements;
use function Groundhogg\isset_not_empty;
use function GroundhoggBookingCalendar\get_default_availability;
use function GroundhoggBookingCalendar\get_time_format;
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
	 * @return bool|mixed|WP_Error
	 * @deprecated
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
		return array_map( function ( $id ) {
			return new Appointment( $id );
		}, $ids );
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
		$slots = $this->get_all_available_slots( $date );

		$slots = $this->sort_slots( $slots );

		if ( ! $slots ) {
			return [];
		}

		$slots = array_filter( $slots, [ $this, 'slot_is_available' ] );

		if ( ! current_user_can( 'edit_appointment' ) ) {
			$slots = $this->make_me_look_busy( $slots, strtotime( $date ) );
		}

		return array_values( $this->get_slots_name( $slots, $timezone ) );
	}


	/**
	 * Check if a slot is taken based on the synced google appointments
	 * and the regular appointments table
	 *
	 * @param $slot
	 */
	public function slot_is_available( $slot ) {

		$connected_gcals = $this->get_google_calendar_list();

		foreach ( $connected_gcals as $gcal ) {
			if ( ! get_db( 'synced_events' )->time_available( $slot['start'], $slot['end'], $gcal ) ) {
				return false;
			}
		}

		return get_db( 'appointments' )->time_available( $slot['start'], $slot['end'], $this->get_id() );
	}

	/**
	 * Adds display title based on the timezone. If timezone is not given then it converts that into local time using local time php function.
	 *
	 * @param $slots
	 * @param $timezone
	 *
	 * @return mixed
	 */
	protected function get_slots_name( $slots, $timezone = false ) {

		$format = get_time_format();

		foreach ( $slots as $i => $slot ) {

			// Show display...
			if ( $timezone ) {
				try {
					$slots[ $i ]['display'] = sprintf( '%s',
						date_i18n( $format, utils()->date_time->convert_to_foreign_time( absint( $slot['start'] ), $timezone ) )
					);
				} catch ( Exception $e ) {
					$slots[ $i ]['display'] = sprintf( '%s',
						date_i18n( $format, convert_to_local_time( absint( $slot['start'] ) ) )
					);
				}
			} else {
				$slots[ $i ]['display'] = sprintf( '%s',
					date_i18n( $format, convert_to_local_time( absint( $slot['start'] ) ) )
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
			$base_time += HOUR_IN_SECONDS * intval( $this->get_meta( 'min_booking_period_count' ) );
		}

		foreach ( $available_periods as $period ) {

			// 09:00
			$start_time = date( 'H:i:s', strtotime( "{$period[0]}:00" ) );
			$start_time = utils()->date_time->convert_to_utc_0( strtotime( "{$str_date_no_hours} {$start_time}" ) );

			if ( $start_time < $base_time ) {
				$start_time = strtotime( date( 'Y-m-d H:00:00', $base_time ) );
			}

			$start_date = new \DateTime( date( 'Y-m-d H:i:s', $start_time ) );

			// 17:00
			$end_time = date( 'H:i:s', strtotime( "{$period[1]}:00" ) );
			$end_time = utils()->date_time->convert_to_utc_0( strtotime( "{$str_date_no_hours} {$end_time}" ) );
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
			$this->google_appointments = $this->get_google_appointments( strtotime( $this->get_min_booking_period() ), strtotime( $this->get_max_booking_period() ) );
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
	 * @param $date  int
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
	 * @param $args array list of args for the appointment
	 *
	 * @return Appointment|false
	 */
	public function schedule_appointment( $args ) {

		$args = wp_parse_args( $args, [
			'contact_id'  => 0,
			'calendar_id' => $this->get_id(),
			'name'        => $this->get_meta( 'default_name' ),
			'status'      => 'scheduled',
			'start_time'  => time(),
			'end_time'    => time() + $this->get_appointment_length( true ),
			'additional'  => '',
		] );

		$args['name'] = do_replacements( $args['name'], $args['contact_id'] );

		$args = apply_filters( 'groundhogg/calendar/schedule_appointment', $args, $this );

		$appointment = new Appointment( $args );

		if ( ! $appointment->exists() ) {
			return false;
		}

		$appointment->update_meta( 'additional', $appointment['additional'] );

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
	 * Get all appointments from this calendar and also any google events
	 */
	public function get_events_for_full_calendar() {

		$local_appointments = $this->get_all_appointments();
		$local_events       = array_map_keys( array_map( function ( $event ) {
			return $event->get_for_full_calendar();
		}, $local_appointments ), function ( $i, $event ) {
			return $event['id'];
		} );

		$google_events = get_db( 'synced_events' )->query( [
			'local_gcalendar_id' => $this->get_google_calendar_list()
		] );

		$google_events = array_map_keys( array_map( function ( $event ) {
			$event = new Synced_Event( $event->event_id );

			return $event->get_for_full_calendar();
		}, $google_events ), function ( $i, $event ) {
			return $event['id'];
		} );

		return array_values( array_merge( $google_events, $local_events ) );
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

}