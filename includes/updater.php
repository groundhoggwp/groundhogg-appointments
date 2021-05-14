<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Email;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
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
			'2.5',
			'2.5.1',
		];
	}

	protected function get_automatic_updates() {
		return [
			'2.5.1'
		];
	}

	protected function get_update_descriptions() {
		return [
			'2.5'   => __( 'Refactor appointment and calendar settings to new formats.' ),
			'2.5.1' => __( 'Update status of previous appointments.' )
		];
	}

	/**
	 * Update the tables
	 *
	 * Update all calendars with slugs
	 */
	public function version_2_5() {

		install_tables();

		install_custom_rewrites();

		global $wpdb;

		// Update BOOKED/APPROVED booking actions to proper action
		$stepmeta = get_db( 'stepmeta' );

		$wpdb->query( sprintf( "UPDATE {$stepmeta->table_name} SET meta_value = '%s' 
WHERE meta_key = 'action' AND meta_value IN ('%s', '%s');",
			Email_Reminder::SCHEDULED,
			Email_Reminder::APPROVED,
			Email_Reminder::BOOKED ) );

		$stepmeta->cache_set_last_changed();

		// Migrate calendar meta keys to new names.
		// $old => $new
		$migrate_calendar_meta_keys = [
			'emails'           => 'email_notifications',
			'reminders'        => 'email_reminders',
			'sms_notification' => 'enable_sms_notifications',
			'sms'              => 'sms_notifications',
		];

		foreach ( $migrate_calendar_meta_keys as $old => $new ) {
			get_db( 'calendarmeta' )->update( [ 'meta_key' => $old ], [ 'meta_key' => $new ] );
		}

		$calendars = get_db( 'calendars' )->query();

		foreach ( $calendars as $calendar ) {

			$calendar = new Calendar( $calendar );

			$calendar->generate_slug();

			// Migrate admin notifications
			$enabled_admin_notifications = [
				'sms'                       => (bool) $calendar->get_meta( 'sms_admin_notification' ),
				Email_Reminder::SCHEDULED   => (bool) $calendar->get_meta( 'booked_admin' ),
				Email_Reminder::RESCHEDULED => (bool) $calendar->get_meta( 'reschedule_admin' ),
				Email_Reminder::CANCELLED   => (bool) $calendar->get_meta( 'cancelled_admin' ),
			];

			$calendar->update_meta( 'enabled_admin_notifications', $enabled_admin_notifications );

			// Change booked -> scheduled in SMS notifications
			// Remove Approved in SMS notifications

			if ( is_sms_plugin_active() ) {
				$sms_notifications = $calendar->get_sms_notification();

				$booked = get_array_var( $sms_notifications, SMS_Reminder::BOOKED );

				if ( $booked ) {
					$sms_notifications[ SMS_Reminder::SCHEDULED ] = $booked;
					unset( $sms_notifications[ SMS_Reminder::BOOKED ] );
					unset( $sms_notifications[ SMS_Reminder::APPROVED ] );
				}

				$calendar->update_meta( 'sms_notifications', $sms_notifications );
			}

			// Change booked -> scheduled in Email notifications
			// Remove Approved in Email notifications
			$email_notifications = $calendar->get_email_notification();

			$booked = get_array_var( $email_notifications, Email_Reminder::BOOKED );

			if ( $booked ) {
				$email_notifications[ Email_Reminder::SCHEDULED ] = $booked;
				unset( $email_notifications[ Email_Reminder::BOOKED ] );
				unset( $email_notifications[ Email_Reminder::APPROVED ] );
			}

			$calendar->update_meta( 'email_notifications', $email_notifications );
		}

	}

	public function version_2_5_1() {
		get_db( 'appointments' )->update( [
			'status' => 'approved'
		], [
			'status' => 'scheduled'
		] );
	}

}