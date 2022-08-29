<?php

namespace GroundhoggBookingCalendar\Classes;

class Slot {

	/**
	 * @var \DateTime
	 */
	protected $start;

	/**
	 * @var \DateTime
	 */
	protected $end;

	public function __construct( \DateTime $start, \DateTime $end ) {
		$this->start = $start;
		$this->end   = $end;
	}

	public function add( \DateInterval $interval ) {
		$this->start->add( $interval );
		$this->end->add( $interval );
	}

	public function subtract( \DateInterval $interval ) {
		$this->start->sub( $interval );
		$this->end->sub( $interval );
	}
}
