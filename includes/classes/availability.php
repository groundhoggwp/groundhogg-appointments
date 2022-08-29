<?php

namespace GroundhoggBookingCalendar\Classes;

interface Availability {

	/**
	 * The unix start time
	 *
	 * @return \DateTime
	 */
	public function get_start_date();

	/**
	 * The ending date
	 *
	 * @return \DateTime
	 */
	public function get_end_date();

	/**
	 * Whether the appointment conflicts
	 *
	 * @param $start \DateTime
	 * @param $end   \DateTime
	 *
	 * @return bool
	 */
	public function conflicts( \DateTime $start, \DateTime $end );

	/**
	 * Whether the appointment is back to back
	 *
	 * @param \DateTime $start
	 * @param \DateTime $end
	 *
	 * @return bool
	 */
	public function is_back_to_back( \DateTime $start, \DateTime $end );

}
