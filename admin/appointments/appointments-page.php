<?php

namespace GroundhoggBookingCalendar\Admin\Appointments;

use Groundhogg\Admin\Admin_Page;
use GroundhoggBookingCalendar\Api\Appointments_Api;
use GroundhoggBookingCalendar\Calendar_Sync;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\Google_Calendar;
use GroundhoggBookingCalendar\Classes\Synced_Event;
use function Groundhogg\action_url;
use function Groundhogg\admin_page_url;
use function Groundhogg\array_map_keys;
use function Groundhogg\array_map_to_class;
use function Groundhogg\array_map_with_keys;
use function Groundhogg\enqueue_filter_assets;
use function Groundhogg\get_array_var;
use function Groundhogg\get_db;
use function Groundhogg\get_object_ids;
use function Groundhogg\get_post_var;
use function Groundhogg\get_request_var;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
use function Groundhogg\id_list_to_class;
use function Groundhogg\Ymd_His;
use function GroundhoggBookingCalendar\get_all_events_for_full_calendar;

class Appointments_Page extends Admin_Page {

	protected function add_ajax_actions() {
		add_action( 'wp_ajax_gh_fetch_appointment', [ $this, 'ajax_fetch_appointment' ] );
		add_action( 'wp_ajax_gh_update_appointment_admin_notes', [ $this, 'ajax_update_appointment_admin_notes' ] );
		add_action( 'wp_ajax_gh_fetch_calendar_config', [ $this, 'ajax_fetch_calendar_config' ] );
		add_action( 'wp_ajax_gh_get_appointment_slots', [ $this, 'ajax_get_appointment_slots' ] );
		add_action( 'wp_ajax_gh_schedule_new_appointment', [ $this, 'ajax_schedule_new_appointment' ] );
	}

	protected function add_additional_actions() {
		// TODO: Implement add_additional_actions() method.
	}

	public function get_slug() {
		return 'gh_appointments';
	}

	public function get_name() {
		return __( 'Appointments', 'groundhogg' );
	}

	public function get_cap() {
		return 'view_appointments';
	}

	public function get_item_type() {
		return 'appointment';
	}

	public function scripts() {

		$calendars = get_db( 'calendars' )->query();
		array_map_to_class( $calendars, Calendar::class );

//		$gcal_ids_to_sync = array_reduce( $calendars, function ( $carry, $calendar ) {
//
//			if ( ! $calendar->is_connected_to_google() ) {
//				return $carry;
//			}
//
//			/**
//			 * @var $calendar Calendar
//			 */
//			return array_unique( array_merge( $carry, $calendar->get_google_calendar_list() ) );
//		}, [] );

//		$gcals                     = id_list_to_class( $gcal_ids_to_sync, Google_Calendar::class );
		$selected_calendars        = get_object_ids( $calendars );
//		$selected_synced_calendars = get_object_ids( $gcals );

		if ( get_url_var( 'selected' ) ) {
			$calendar                  = new Calendar( get_url_var( 'selected' ) );
			$selected_calendars        = [ $calendar->get_id() ];
			$selected_synced_calendars = wp_parse_id_list( $calendar->get_google_calendar_list() );
		}

		wp_enqueue_editor();
		wp_enqueue_media();
		wp_enqueue_script( 'groundhogg-appointments-admin' );
		wp_localize_script( 'groundhogg-appointments-admin', 'GroundhoggAppointments', [
			'routes'    => [
				'appointments' => rest_url( Appointments_Api::NAME_SPACE . '/appointments' ),
				'calendars'    => rest_url( Appointments_Api::NAME_SPACE . '/calendars' )
			],
			'calendars' => [
				'local'  => $calendars,
				'synced' => [],
			],
			'selected'  => [
				'local'  => $selected_calendars,
				'synced' => $selected_synced_calendars,
			],
		] );
		wp_enqueue_style( 'groundhogg-fullcalendar' );
		wp_enqueue_style( 'groundhogg-calender-admin' );
		wp_enqueue_style( 'groundhogg-appointments-admin' );
		enqueue_filter_assets();
		wp_enqueue_style( 'groundhogg-admin' );
	}

	public function help() {
		// TODO: Implement help() method.
	}

	public function page() {
		?>
        <div id="appointments-app"></div><?php
	}

	public function get_priority() {
		return 49;
	}

	public function view() {
		// TODO: Implement view() method.
	}
}
