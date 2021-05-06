<?php

namespace GroundhoggBookingCalendar\Steps;

use Groundhogg\Contact;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\get_posts_for_select;
use function Groundhogg\html;
use Groundhogg\Step;
use Groundhogg\Steps\Benchmarks\Benchmark;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Email_Reminder;

class Booking_Calendar extends Benchmark {

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
		html()->start_form_table();
		html()->start_row();
		html()->th( __( 'Run when appointment status changed for this calendar:', 'groundhogg-calendar' ) );
		html()->td(
			[
				$this->dropdown_calendar( [ 'selected' => $this->get_setting( 'calendar' ) ] ),
			]
		);
		html()->end_row();
		html()->start_row();
		html()->th( __( 'For which status should this run:', 'groundhogg-calendar' ) );
		html()->td(
			[
				html()->dropdown( [
					'id'       => $this->setting_id_prefix( 'action' ),
					'name'     => $this->setting_name_prefix( 'action' ),
					'options'  => [
						Email_Reminder::SCHEDULED   => __( 'Appointment Scheduled' ),
						Email_Reminder::RESCHEDULED => __( 'Appointment Rescheduled' ),
						Email_Reminder::CANCELLED   => __( 'Appointment Cancelled' ),
					],
					'selected' => $this->get_setting( 'action' ),
				] ),
			]
		);
		html()->end_row();
		html()->end_form_table();
	}

	public function dropdown_calendar( $args ) {
		$a         = wp_parse_args( $args, array(
			'name'        => $this->setting_name_prefix( 'calendar' ),
			'id'          => $this->setting_id_prefix( 'calendar' ),
			'selected'    => 0,
			'class'       => 'gh_calendar-picker gh-select2',
			'multiple'    => false,
			'placeholder' => __( 'Please Select a calendar', 'groundhogg-calendar' ),
			'tags'        => false,
		) );
		$calendars = get_db( 'calendars' )->query();
		foreach ( $calendars as $calendar ) {
			$a['data'][ $calendar->ID ] = $calendar->name;
		}

		return html()->select2( $a );
	}

	public function save( $step ) {
		$this->save_setting( 'calendar', absint( $this->get_posted_data( 'calendar' ) ) );
		$this->save_setting( 'action', $this->get_posted_data( 'action' ) );
	}


	protected function get_the_contact() {
		$contact = get_contactdata( $this->get_data( 'contact_id' ) );
		if ( ! $contact->exists() ) {
			return false;
		}

		return $contact;
	}

	protected function can_complete_step() {
		return absint( $this->get_data( 'calendar_id' ) ) === absint( $this->get_setting( 'calendar' ) )
		       && $this->get_data( 'action' ) === $this->get_setting( 'action' );
	}


	/**
	 * Setup the benchmark when the appt is cancelled, rescheduled, or first scheduled
	 *
	 * @param $appointment Appointment
	 */
	public function setup( $appointment ) {

		$appointment = is_int( $appointment ) ? new Appointment( $appointment ) : $appointment;

		$this->add_data( 'calendar_id', $appointment->get_calendar_id() );
		$this->add_data( 'contact_id', $appointment->get_contact_id() );

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