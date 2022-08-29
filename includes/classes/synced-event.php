<?php

namespace GroundhoggBookingCalendar\Classes;


use Google_Service_Calendar_Event;
use function Groundhogg\convert_to_utc_0;
use function Groundhogg\get_time;
use function Groundhogg\Ymd_His;

class Synced_Event implements Availability {

	protected $start_time;
	protected $end_time;
	protected $summary;
	protected $description;
	protected $is_all_day;
	protected $time_zone;
	public $id;

	/**
	 * @param $event Google_Service_Calendar_Event
	 */
	public function __construct( $event ) {

		$this->start_time = strtotime( $event->getStart()->getDateTime() );
		$this->end_time   = strtotime( $event->getEnd()->getDateTime() );

		// Handle all day event
		if ( ! $this->start_time && ! $this->end_time ) {
			$this->is_all_day = true;
			$this->start_time = $event->getStart()->getDate();
			$this->end_time   = $event->getEnd()->getDate();
			$this->time_zone  = $event->getStart()->getTimeZone();
		}

		$this->description = $event->getDescription();
		$this->summary     = $event->getSummary();
		$this->id          = $event->getId();
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

		// handle all day conflict
		if ( $this->is_all_day ) {
			// start_time will be a string Y-m-d string
			if ( $this->start_time == $start->format( 'Y-m-d' ) ) {
				return true;
			}
		}

		return
			// Start is within range
			( $start >= $this->start_time && $start < $this->end_time ) ||
			// End is within range
			( $end > $this->start_time && $end <= $this->end_time ) ||
			// the given start and end time are within the slot
			( $start >= $this->start_time && $end <= $this->end_time ) ||
			// the slot is within the given time
			( $start <= $this->start_time && $end >= $this->end_time );
	}

	public function get_for_full_calendar() {

		return [
			'id'            => $this->id,
			'title'         => $this->summary,
			'description'   => $this->description,
			'start'         => is_int( $this->start_time ) ? date( DATE_RFC3339, $this->start_time ) : $this->start_time,
			'end'           => is_int( $this->end_time ) ? date( DATE_RFC3339, $this->end_time ) : $this->end_time,
			'editable'      => false,
			'allDay'        => ( $this->end_time - $this->start_time ) % DAY_IN_SECONDS === 0,
			'color'         => '#0073aa',
			'extendedProps' => [

			]
		];
	}

	public function __serialize() {
		return [
			'start'       => $this->start_time,
			'end'         => $this->end_time,
			'id'          => $this->id,
			'allDay'      => $this->is_all_day,
			'summary'     => $this->summary,
			'description' => $this->description,
			'timeZone'    => $this->time_zone,
		];
	}

	public function __unserialize( $data ) {
		$this->start_time  = $data['start'];
		$this->end_time    = $data['end'];
		$this->id          = $data['id'];
		$this->is_all_day  = $data['allDay'];
		$this->summary     = $data['summary'];
		$this->description = $data['description'];
		$this->time_zone   = $data['timeZone'];
	}

	public function get_start_date() {
		return new \DateTime( Ymd_His( $this->start_time ) );
	}

	public function get_end_date() {
		return new \DateTime( Ymd_His( $this->end_time ) );
	}

	public function is_back_to_back( \DateTime $start, \DateTime $end ) {
		return $start == $this->get_end_date() || $end == $this->get_start_date();
	}
}
