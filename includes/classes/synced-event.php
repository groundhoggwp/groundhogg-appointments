<?php

namespace GroundhoggBookingCalendar\Classes;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Oauth2;
use Groundhogg\Base_Object;
use Groundhogg\DB\DB;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\DB\Google_Connections;
use function Groundhogg\convert_to_local_time;
use function Groundhogg\get_array_var;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_db;
use function Groundhogg\isset_not_empty;
use function Groundhogg\utils;
use function Groundhogg\Ymd_His;

class Synced_Event extends Base_Object {

	protected function post_setup() {
		$numeric_keys = [
			'start_time',
			'end_time',
			'last_synced',
		];

		foreach ( $numeric_keys as $key ) {
			$this->$key = intval( $this->$key );
		}
	}

	protected function get_db() {
		return get_db( 'synced_events' );
	}

	public function get_dates_from_event( $event ) {
		// The start time will either be a proper daytime or the beginning of a day, which is fine.
		$start = strtotime( $event->getStart()->getDateTime() );
		$end   = strtotime( $event->getEnd()->getDateTime() );

		// handle all day event
		if ( ! $start || ! $end ){
			$start = strtotime( $event->getStart()->getDate() );
			$end   = strtotime( $event->getEnd()->getDate() );

			$end   = utils()->date_time->convert_to_utc_0( $end );
			$start = utils()->date_time->convert_to_utc_0( $start );
		}

		return [
			'start_time'        => $start,
			'end_time'          => $end,
			'start_time_pretty' => Ymd_His( $start ),
			'end_time_pretty'   => Ymd_His( $end ),
		];
	}

	/**
	 * @param $event    Google_Service_Calendar_Event
	 * @param $gcal     Google_Calendar
	 */
	public function create_from_event( $event, $gcal ) {

		$args = wp_parse_args( $this->get_dates_from_event( $event ), [
			'event_id'           => $event->getId(),
			'summary'            => $event->getSummary(),
			'local_gcalendar_id' => $gcal->get_id(),
			'google_calendar_id' => $gcal->google_calendar_id,
		] );

		$this->create( $args );
	}

	/**
	 * @param $event Google_Service_Calendar_Event
	 */
	public function update_from_event( $event ) {

		$times = $this->get_dates_from_event( $event );

		if ( $this->start_time !== $times['start_time'] || $this->end_time !== $times['end_time'] ) {
			$this->update( wp_parse_args( $times, [
				'last_synced' => time()
			] ) );
		}
	}

	public function get_id() {
		return $this->event_id;
	}

	public function get_for_full_calendar() {

		return [
			'id'       => $this->event_id,
			'title'    => $this->summary,
			'start'    => $this->start_time * 1000,
			'end'      => $this->end_time * 1000,
			'editable' => false,
			'allDay'   => ( $this->end_time - $this->start_time ) % DAY_IN_SECONDS === 0,
			'color'    => '#0073aa',
		];
	}
}
