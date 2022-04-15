<?php

namespace GroundhoggBookingCalendar\Admin\Appointments;

use Groundhogg\Admin\Admin_Page;
use GroundhoggBookingCalendar\Calendar_Sync;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\Synced_Event;
use function Groundhogg\action_url;
use function Groundhogg\admin_page_url;
use function Groundhogg\array_map_keys;
use function Groundhogg\array_map_with_keys;
use function Groundhogg\get_array_var;
use function Groundhogg\get_db;
use function Groundhogg\get_post_var;
use function Groundhogg\get_request_var;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
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
		return 'view_appointment';
	}

	public function get_item_type() {
		return 'appointment';
	}

	public function scripts() {

		switch ( $this->get_current_action() ) {
			case 'view':
                wp_enqueue_editor();
                wp_enqueue_media();
				wp_enqueue_script( 'groundhogg-appointments-admin' );
				wp_localize_script( 'groundhogg-appointments-admin', 'GroundhoggAppointments', [
					'events'      => get_all_events_for_full_calendar(),
					'spinner'     => '<div><div class="loader-overlay"></div><div class="loader-wrap"><span class="gh-loader"></span></div></div>',
					'appointment' => new Appointment( get_url_var( 'appointment' ) )
				] );
				wp_enqueue_style( 'groundhogg-fullcalendar' );
				wp_enqueue_style( 'groundhogg-calender-admin' );
				wp_enqueue_style( 'groundhogg-appointments-admin' );
				break;
			case 'add':

				$object = [
					'date'       => Ymd_His(),
					'datepicker' => [
						'start_of_week' => get_option( 'start_of_week' ),
						'day_names'     => [
							__( 'SUN', 'groundhogg-calendar' ),
							__( 'MON', 'groundhogg-calendar' ),
							__( 'TUE', 'groundhogg-calendar' ),
							__( 'WED', 'groundhogg-calendar' ),
							__( 'THU', 'groundhogg-calendar' ),
							__( 'FRI', 'groundhogg-calendar' ),
							__( 'SAT', 'groundhogg-calendar' )
						],
					]
				];

				if ( $calendar_id = get_url_var( 'calendar_id' ) ) {
					$calendar = new Calendar( $calendar_id );

					$object = wp_parse_args( $object, [
						'config'   => [
							'min_date'       => $calendar->get_min_booking_period( true ),
							'max_date'       => $calendar->get_max_booking_period( true ),
							'disabled_days'  => $calendar->get_dates_no_slots(),
							'business_hours' => $calendar->get_business_hours(),
						],
						'calendar' => $calendar,
					] );
				}

				if ( $contact_id = get_url_var( 'contact' ) ) {
					$object = wp_parse_args( $object, [
						'contact_id' => $contact_id
					] );
				}

				wp_enqueue_script( 'groundhogg-new-appointment-admin' );
				wp_localize_script( 'groundhogg-new-appointment-admin', 'GroundhoggAppointments', $object );

				wp_enqueue_style( 'jquery-ui' );
				wp_enqueue_style( 'groundhogg-new-appointment-admin' );

				break;
		}


		wp_enqueue_style( 'groundhogg-admin' );
	}

	public function help() {
		// TODO: Implement help() method.
	}

	public function ajax_fetch_appointment() {

		if ( ! current_user_can( 'view_appointment' ) ) {
			return;
		}

		$appointment_id = get_post_var( 'appointment' );

		ob_start();

		$appointment = new Appointment( $appointment_id, 'uuid' );

		// Get from google?
		if ( ! $appointment->exists() ) {
			$appointment = new Synced_Event( $appointment_id, 'event_id' );

			if ( ! $appointment->exists() ) {
				wp_send_json_error();
			}

			$appointment->sync_all_details();

			if ( $appointment->has_errors() ) {
				wp_send_json_error( $appointment->get_last_error() );
			}

			include __DIR__ . '/synced.php';
		} else {

			include __DIR__ . '/view.php';

		}

		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html, 'appointment' => $appointment ] );
	}

	public function ajax_update_appointment_admin_notes() {

		if ( ! current_user_can( 'edit_appointment' ) ) {
			return;
		}

		$appointment_id = get_post_var( 'appointment' );
		$appointment    = new Appointment( $appointment_id, 'uuid' );

		// Get from google?
		if ( ! $appointment->exists() ) {
			wp_send_json_error();
		}

		$notes = sanitize_text_field( get_post_var( 'admin_notes' ) );
		$appointment->update_meta( 'admin_notes', $notes );

		wp_send_json_success( [ 'appointment' => $appointment ] );
	}

	public function ajax_fetch_calendar_config() {
		if ( ! current_user_can( 'add_appointment' ) ) {
			return;
		}

		$calendar_id = get_post_var( 'calendar' );
		$calendar    = new Calendar( $calendar_id );

		// Get from google?
		if ( ! $calendar->exists() ) {
			wp_send_json_error();
		}

		wp_send_json_success( [
			'config'   => [
				'min_date'       => $calendar->get_min_booking_period( true ),
				'max_date'       => $calendar->get_max_booking_period( true ),
				'disabled_days'  => $calendar->get_dates_no_slots(),
				'business_hours' => $calendar->get_business_hours(),
			],
			'calendar' => $calendar
		] );
	}

	public function get_appointment_slots() {

		if ( ! current_user_can( 'add_appointment' ) ) {
			return;
		}

		$calendar_id = get_request_var( 'calendar_id' );
		$calendar    = new Calendar( $calendar_id );

		// Get from google?
		if ( ! $calendar->exists() ) {
			return;
		}

		$date  = sanitize_text_field( get_post_var( 'date', Ymd_His() ) );
		$slots = $calendar->get_appointment_slots( $date );

		if ( empty( $slots ) ):
			?>
            <div class="notice notice-error">
            <p><?php _e( 'Sorry, no time slots are available on this date.', 'groundhogg-calendar' ); ?></p>
            </div><?php
		else:
			foreach ( $slots as $i => $slot ):

				echo html()->input( [
					'type'      => 'button',
					'class'     => 'appointment-time button',
					'name'      => 'appointment_time',
					'data-from' => $slot['start'],
					'data-to'   => $slot['end'],
					'value'     => $slot['display'],
				] );

			endforeach;

		endif;
	}

	public function ajax_get_appointment_slots() {
		if ( ! current_user_can( 'add_appointment' ) ) {
			wp_send_json_error();
		}

		ob_start();

		$this->get_appointment_slots();

		$html = ob_get_clean();

		wp_send_json_success( [
			'html' => $html
		] );
	}


	public function ajax_schedule_new_appointment() {

		if ( ! current_user_can( 'add_appointment' ) ) {
			wp_send_json_error();
		}

		$calendar_id = get_request_var( 'calendar_id' );
		$calendar    = new Calendar( $calendar_id );

		if ( ! $calendar->exists() ) {
			wp_send_json_error();
		}

		$appointment = $calendar->schedule_appointment( [
			'contact_id' => absint( get_post_var( 'contact_id' ) ),
			'start_time' => absint( get_post_var( 'from' ) ),
			'end_time'   => absint( get_post_var( 'to' ) ),
		] );

		if ( ! $appointment->exists() ) {
			wp_send_json_error();
		}

		wp_send_json_success( [
			'appointment' => $appointment,
			'redirect'    => admin_page_url( 'gh_appointments', [ 'appointment' => $appointment->get_id() ] )
		] );
	}

	public function process_sync_events() {
		Calendar_Sync::sync();

		return false;
	}

	protected function get_title_actions() {
		return [
			[
				'link'   => action_url( 'sync_events' ),
				'action' => __( 'Sync', 'groundhogg-calendar' ),
				'target' => '_self',
			],
			[
				'link'   => $this->admin_url( [ 'action' => 'add' ] ),
				'action' => __( 'Schedule New', 'groundhogg' ),
				'target' => '_self',
			]
		];
	}

	public function view() {

		$appointment = new Appointment( get_url_var( 'appointment' ) );

		include __DIR__ . '/filters.php'

		?>
        <div id="calendar-wrap" style="">
            <div id="calendar"></div>
        </div>
		<?php
	}

	public function add() {
		?>
        <div class="gh-tools-wrap">
            <p class="tools-help"><?php _e( 'Schedule a New Appointment', 'groundhogg-calendar' ); ?></p>
            <div class="gh-tools-box">
                <p><b><?php _e( 'Which calendar should the appointment be added to?', 'groundhogg-calendar' ) ?></b></p>
                <p><?php

					$calendars = get_db( 'calendars' )->query( [
						'user_id' => current_user_can( 'view_own_calendar' ) ? get_current_user_id() : false,
					] );

					echo html()->select2( [
						'options'     => array_map_with_keys( array_map_keys( $calendars, function ( $i, $cal ) {
							return $cal->ID;
						} ), function ( $cal ) {
							return $cal->name;
						} ),
						'selected'    => get_url_var( 'calendar_id' ),
						'name'        => 'calendar_id',
						'id'          => 'calendar-id',
						'placeholder' => __( 'Select a calendar', 'groundhogg-calendar' )
					] );

					?></p>
                <p><b><?php _e( 'At what time?', 'groundhogg-calendar' ) ?></b></p>
                <div class="booking-dates">
                    <div id="calendar"></div>
                    <div id="slots">
						<?php $this->get_appointment_slots(); ?>
                    </div>
                </div>
                <p><b><?php _e( 'Select a guest to invite', 'groundhogg-calendar' ) ?></b></p>
                <p>
					<?php echo html()->dropdown_contacts( [
						'id'       => 'contact-id',
						'selected' => [ absint( get_url_var( 'contact' ) ) ]
					] ); ?>
                </p>
                <p>
					<?php echo html()->submit( [
						'text'     => __( 'Schedule', 'groundhogg-calendar' ),
						'id'       => 'schedule',
						'disabled' => true,
					] ) ?>
                </p>
                <div id="loader-wrap" class="hidden">
                    <div class="loader-overlay"></div>
                    <div class="loader-wrap"><span class="gh-loader"></span></div>
                </div>
            </div>
        </div>
		<?php
	}

	public function get_priority() {
		return 49;
	}
}
