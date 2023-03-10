<?php

namespace GroundhoggBookingCalendar\Steps;

use Groundhogg\Steps\Benchmarks\Benchmark;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
use function Groundhogg\array_bold;
use function Groundhogg\array_map_to_class;
use function Groundhogg\array_map_to_method;
use function Groundhogg\bold_it;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\html;
use function Groundhogg\orList;

class Booking_Calendar extends Benchmark {

	public function get_sub_group() {
		return 'forms';
	}

	public function get_name() {
		return __( 'Booking Calendar', 'groundhogg-calendar' );
	}

	public function get_type() {
		return 'gh_appointments';
	}

	public function get_description() {
		return __( 'Run automation based on appointment booked in booking calendar.', 'groundhogg-calendar' );
	}

	public function get_icon() {
		return GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'images/calendar.png';
	}

	protected function get_complete_hooks() {
		return [
			'groundhogg/calendar/appointment/scheduled'   => 1,
			'groundhogg/calendar/appointment/rescheduled' => 1,
			'groundhogg/calendar/appointment/cancelled'   => 1,
		];
	}

	public function settings( $step ) {

		echo html()->e( 'p', [], __( 'Run when an appointment is...', 'groundhogg-calendar' ) );

		echo html()->select2( [
			'id'       => $this->setting_id_prefix( 'action' ),
			'name'     => $this->setting_name_prefix( 'action' ) . '[]',
			'options'  => [
				Email_Reminder::SCHEDULED   => __( 'Scheduled' ),
				Email_Reminder::RESCHEDULED => __( 'Rescheduled' ),
				Email_Reminder::CANCELLED   => __( 'Cancelled' ),
			],
			'selected' => $this->get_setting( 'action', [] ),
			'multiple' => true,
		] );

		echo html()->e( 'p', [], __( 'For any of the following calendars...', 'groundhogg-calendar' ) );

		echo $this->dropdown_calendar( [ 'selected' => wp_parse_id_list( $this->get_setting( 'calendar', [] ) ) ] );

		?><p></p><?php
	}

	public function generate_step_title( $step ) {

		$actions = [
			Email_Reminder::SCHEDULED   => __( 'Scheduled' ),
			Email_Reminder::RESCHEDULED => __( 'Rescheduled' ),
			Email_Reminder::CANCELLED   => __( 'Cancelled' ),
		];

		$calendar_ids = wp_parse_id_list( $this->get_setting( 'calendar', [] ) );
		$calendars    = array_map_to_method( array_map_to_class( $calendar_ids, Calendar::class ), 'get_name' );
		$actions      = array_map( function ( $action ) use ( $actions ) {
			return $actions[ $action ];
		}, wp_parse_list( $this->get_setting( 'action', [] ) ) );

		if ( empty( $actions ) ) {
			return 'Calendar event';
		}

        $calendars = orList( array_bold( $calendars ) );

		if ( empty( $calendars ) ) {
			$calendars = bold_it( 'any calendar' );
		}

		return sprintf( '%s an appointment for %s', orList( array_bold( $actions ) ), $calendars );
	}

	public function dropdown_calendar( $args ) {
		$a         = wp_parse_args( $args, array(
			'id'          => $this->setting_id_prefix( 'calendar' ),
			'name'        => $this->setting_name_prefix( 'calendar' ) . '[]',
			'selected'    => 0,
			'class'       => 'gh_calendar-picker gh-select2',
			'multiple'    => true,
			'placeholder' => __( 'Select a calendar', 'groundhogg-calendar' ),
			'tags'        => false,
		) );
		$calendars = get_db( 'calendars' )->query();
		foreach ( $calendars as $calendar ) {
			$a['data'][ $calendar->ID ] = $calendar->name;
		}

		return html()->select2( $a );
	}

	public function save( $step ) {
		$this->save_setting( 'calendar', wp_parse_id_list( $this->get_posted_data( 'calendar', [] ) ) );
		$this->save_setting( 'action', array_map( 'sanitize_text_field', $this->get_posted_data( 'action', [] ) ) );
	}


	protected function get_the_contact() {
		return $this->get_data( 'contact' );
	}

	protected function can_complete_step() {

		$actions   = wp_parse_list( $this->get_setting( 'action', [] ) );
		$calendars = wp_parse_id_list( $this->get_setting( 'calendar', [] ) );

		return in_array( $this->get_data( 'action' ), $actions ) && ( empty( $calendars ) || in_array( $this->get_data( 'calendar' ), $calendars ) );
	}


	/**
	 * Setup the benchmark when the appt is cancelled, rescheduled, or first scheduled
	 *
	 * @param $appointment Appointment
	 */
	public function setup( $appointment ) {

		$appointment = is_a( $appointment, Appointment::class ) ? $appointment : new Appointment( $appointment );

		$this->add_data( 'calendar', $appointment->get_calendar_id() );
		$this->add_data( 'contact', $appointment->get_contact() );

		switch ( current_action() ) {
			case 'groundhogg/calendar/appointment/scheduled':
				$this->add_data( 'action', Email_Reminder::SCHEDULED );
				break;
			case 'groundhogg/calendar/appointment/rescheduled':
				$this->add_data( 'action', Email_Reminder::RESCHEDULED );
				break;
			case 'groundhogg/calendar/appointment/cancelled':
				$this->add_data( 'action', Email_Reminder::CANCELLED );
				break;
		}

	}

}
