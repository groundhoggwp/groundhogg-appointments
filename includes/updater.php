<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Email;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Appointment_Reminder;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use function Groundhogg\emergency_init_dbs;
use function Groundhogg\get_array_var;
use function Groundhogg\get_db;
use function Groundhogg\get_post_var;
use function Groundhogg\install_custom_rewrites;
use function Groundhogg\words_to_key;
use function Groundhogg\get_email_templates;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;

class Updater extends \Groundhogg\Updater {

	/**
	 * A unique name for the updater to avoid conflicts
	 *
	 * @return string
	 */
	protected function get_updater_name() {
		return words_to_key( GROUNDHOGG_BOOKING_CALENDAR_NAME );
	}

	/**
	 * Get a list of updates which are available.
	 *
	 * @return string[]
	 */
	protected function get_available_updates() {
		return [
			'2.5.1',
			'2.5.4',
			'2.5.4.1',
			'2.6',
		];
	}

	protected function get_automatic_updates() {
		return [
			'2.5.1',
			'2.5.4.1',
			'2.6'
		];
	}

	protected function get_update_descriptions() {
		return [
			'2.5.1'   => __( 'Update status of previous appointments.' ),
			'2.5.4'   => __( 'Keep track of Google calendar connection status.' ),
			'2.5.4.1' => __( 'Remove cap from admin role.' ),
			'2.6'     => __( 'Change the way Google events are synced' ),
		];
	}

	/**
	 * Update the synced events table to the new format
	 *
	 * @return void
	 */
	public function version_2_6() {
		get_db( 'synced_events' )->force_drop();
		get_db( 'synced_events' )->create_table();
	}

	/**
	 * These roles should not have this cap
	 */
	public function version_2_5_4_1() {
		get_role( 'administrator' )->remove_cap( 'view_own_calendar' );
	}

	public function version_2_5_1() {
		get_db( 'appointments' )->update( [
			'status' => 'approved'
		], [
			'status' => 'scheduled'
		] );
	}

	public function version_2_5_4() {
		// Add status col
		get_db( 'google_connections' )->create_table();
		// Update
		get_db( 'google_connections' )->update( [
			'status' => ''
		], [
			'status' => 'active'
		] );
	}

}
