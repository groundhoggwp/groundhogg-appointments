<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Event;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use function Groundhogg\get_db;

class Reminders_And_Notifications {

	public function __construct() {
		add_action( 'groundhogg/calendar/appointment/scheduled', [ $this, 'appointment_scheduled' ], 10 );
		add_action( 'groundhogg/calendar/appointment/rescheduled', [ $this, 'appointment_rescheduled' ], 10 );
		add_action( 'groundhogg/calendar/appointment/cancelled', [ $this, 'appointment_cancelled' ], 10 );
	}

	/**
	 * Handle the scheduling and sending of appointment reminders for both the
	 * contact and admins when an appointment is scheduled for the first time
	 *
	 * @param Appointment $appointment
	 */
	public function appointment_scheduled( $appointment ) {

		// Schedule reminders
		$this->schedule_reminders( $appointment );

		// Send notifications
		$this->send_notifications( $appointment, Email_Reminder::SCHEDULED );
	}

	/**
	 * Handle the scheduling and sending of appointment reminders for both the
	 * contact and admins when an appointment is rescheduled
	 *
	 * @param Appointment $appointment
	 */
	public function appointment_rescheduled( $appointment ) {

		// Cancel any pending reminders
		$this->cancel_reminders( $appointment );

		// Schedule new reminders
		$this->schedule_reminders( $appointment );

		// Send relevant notifications
		$this->send_notifications( $appointment, Email_Reminder::RESCHEDULED );
	}

	/**
	 * Handle the scheduling and sending of appointment reminders for both the
	 * contact and admins when an appointment is cancelled
	 *
	 * @param Appointment $appointment
	 */
	public function appointment_cancelled( $appointment ) {

		// Cancel any upcoming reminders that aren't cancellation notifications
		$this->cancel_reminders( $appointment );

		// Send cancellation notifications
		$this->send_notifications( $appointment, Email_Reminder::CANCELLED );
	}

	/**
	 * Cancel any active reminders
	 *
	 * @param Appointment $appointment
	 */
	public function cancel_reminders( $appointment ) {

		// Cancel notifications
		get_db( 'event_queue' )->update( [
			'funnel_id'  => $appointment->get_id(),
			'contact_id' => $appointment->get_contact_id(),
			'event_type' => Email_Reminder::NOTIFICATION_TYPE,
			'status'     => Event::WAITING,
		], [
			'status' => Event::CANCELLED
		] );

		// If SMS is active cancel those notifactions as well
		if ( is_sms_plugin_active() ) {

			get_db( 'event_queue' )->update( [
				'funnel_id'  => $appointment->get_id(),
				'contact_id' => $appointment->get_contact_id(),
				'event_type' => SMS_Reminder::NOTIFICATION_TYPE,
				'status'     => Event::WAITING,
			], [
				'status' => Event::CANCELLED
			] );

		}
	}

	/**
	 * Schedule any reminder emails, not notifications
	 *
	 * @param Appointment $appointment
	 */
	public function schedule_reminders( $appointment ) {

		$email_reminders = $appointment->get_calendar()->get_email_reminders();

		if ( ! empty( $email_reminders ) ) {
			foreach ( $email_reminders as $reminder ) {
				// Calc time...
				switch ( $reminder['when'] ) {
					default:
					case 'before':
						$time = strtotime( sprintf( "-%d %s", $reminder['number'], $reminder['period'] ), $appointment->get_start_time() );
						if ( $time > time() ) {
							send_email_reminder_notification( $reminder['email_id'], $appointment, $time );
						}
						break;
					case 'after':
						$time = strtotime( sprintf( "+%d %s", $reminder['number'], $reminder['period'] ), $appointment->get_end_time() );
						if ( $time > time() ) {
							send_email_reminder_notification( $reminder['email_id'], $appointment, $time );
						}
						break;
				}
			}
		}

		if ( $appointment->get_calendar()->are_sms_notifications_enabled() ) {

			$sms_reminders = $appointment->get_calendar()->get_sms_reminders();

			if ( ! empty( $sms_reminders ) ) {
				foreach ( $sms_reminders as $reminder ) {
					// Calc time...
					switch ( $reminder['when'] ) {
						default:
						case 'before':
							$time = strtotime( sprintf( "-%d %s", $reminder['number'], $reminder['period'] ), $appointment->get_start_time() );
							if ( $time > time() ) {
								send_sms_reminder_notification( $reminder['sms_id'], $appointment, $time );
							}
							break;
						case 'after':
							$time = strtotime( sprintf( "+%d %s", $reminder['number'], $reminder['period'] ), $appointment->get_end_time() );
							if ( $time > time() ) {
								send_sms_reminder_notification( $reminder['sms_id'], $appointment, $time );
							}
							break;
					}
				}
			}
		}
	}

	/**
	 * Send all the notifications for the appointment
	 *
	 * @param Appointment $appointment
	 * @param string      $which
	 */
	public function send_notifications( $appointment, $which ) {

		// This one goes to the client
		send_email_reminder_notification( $appointment->get_calendar()->get_email_notification( $which ), $appointment );

		// Send SMS to client if enabled
		if ( $appointment->get_calendar()->are_sms_notifications_enabled() ) {
			send_sms_reminder_notification( $appointment->get_calendar()->get_sms_notification( $which ), $appointment );
		}

		// This one goes to the admin
		if ( $appointment->get_calendar()->is_admin_notification_enabled( $which ) ) {
			send_appointment_admin_notifications( $appointment, $which );
		}
	}
}
