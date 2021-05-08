<?php

namespace GroundhoggBookingCalendar\Admin\Appointments;

use Groundhogg\Admin\Admin_Page;
use Groundhogg\Base_Object;
use GroundhoggBookingCalendar\Calendar_Sync;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Synced_Event;
use function Groundhogg\action_url;
use function Groundhogg\get_post_var;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
use function GroundhoggBookingCalendar\get_all_events_for_full_calendar;

class Appointments_Page extends Admin_Page {

	protected function add_ajax_actions() {
		add_action( 'wp_ajax_gh_fetch_appointment', [ $this, 'ajax_fetch_appointment' ] );
		add_action( 'wp_ajax_gh_update_appointment_admin_notes', [ $this, 'ajax_update_appointment_admin_notes' ] );
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
		wp_enqueue_script( 'groundhogg-appointments-admin' );
		wp_localize_script( 'groundhogg-appointments-admin', 'GroundhoggAppointments', [
			'events'      => get_all_events_for_full_calendar(),
			'spinner'     => '<div><div class="loader-overlay"></div><div class="loader-wrap"><span class="gh-loader"></span></div></div>',
			'appointment' => new Appointment( get_url_var( 'appointment' ) )
		] );
		wp_enqueue_style( 'groundhogg-fullcalendar' );
		wp_enqueue_style( 'groundhogg-calender-admin' );
		wp_enqueue_style( 'groundhogg-appointments-admin' );
		wp_enqueue_style( 'groundhogg-admin' );
	}

	public function help() {
		// TODO: Implement help() method.
	}

	public function ajax_fetch_appointment() {
		$appointment_id = get_post_var( 'appointment' );

		ob_start();

		$appointment = new Appointment( $appointment_id, 'uuid' );

		// Get from google?
		if ( ! $appointment->exists() ) {
			$appointment = new Synced_Event( $appointment_id, 'event_id' );

			if ( ! $appointment->exists() ) {
				wp_send_json_error();
			}

			include __DIR__ . '/synced.php';
		} else {

			include __DIR__ . '/view.php';

		}

		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html, 'appointment' => $appointment ] );
	}

	public function ajax_update_appointment_admin_notes() {
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

	public function process_sync_events() {
		Calendar_Sync::sync();

		return false;
	}

	protected function get_title_actions() {
		return [
			[
				'link'   => action_url( 'sync_events' ),
				'action' => __( 'Sync', 'groundhogg' ),
				'target' => '_self',
			]
		];
	}

	public function view() {

		$appointment = new Appointment( get_url_var( 'appointment' ) );

		?>
		<div class="columns">
			<div id="calendar-wrap" class="postbox" style="">
				<div id="calendar"></div>
			</div>
			<div id="appointment" class="postbox">
				<?php if ( $appointment->exists() ): ?>
					<?php include __DIR__ . '/view.php' ?>
				<?php else: ?>
					<p class="instructions">
						<?php _e( 'Click on an appointment to bring up the details.', 'groundhogg-calendar' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function get_priority() {
		return 49;
	}
}