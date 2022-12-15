<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Event;
use Groundhogg\Event_Queue_Item;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Appointment_Reminder;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use function Groundhogg\get_db;

class Reminders_And_Notifications {

	public function __construct() {
		add_action( 'groundhogg/calendar/appointment/scheduled', [ $this, 'appointment_scheduled' ], 10 );
		add_action( 'groundhogg/calendar/appointment/rescheduled', [ $this, 'appointment_rescheduled' ], 10 );
		add_action( 'groundhogg/calendar/appointment/cancelled', [ $this, 'appointment_cancelled' ], 10 );
	}

	public const SCHEDULED = 'scheduled';
	public const RESCHEDULED = 'rescheduled';
	public const CANCELLED = 'cancelled';

	/**
	 * Handle the scheduling and sending of appointment reminders for both the
	 * contact and admins when an appointment is scheduled for the first time
	 *
	 * @param Appointment $appointment
	 */
	public function appointment_scheduled( $appointment ) {

		current_appointment( $appointment );

		// Schedule reminders
		$this->schedule_reminders();

		// Send notifications
		$this->send_notifications( self::SCHEDULED );
	}

	/**
	 * Handle the scheduling and sending of appointment reminders for both the
	 * contact and admins when an appointment is rescheduled
	 *
	 * @param Appointment $appointment
	 */
	public function appointment_rescheduled( $appointment ) {

		current_appointment( $appointment );

		// Cancel any pending reminders
		$this->cancel_reminders();

		// Schedule new reminders
		$this->schedule_reminders();

		// Send relevant notifications
		$this->send_notifications( self::RESCHEDULED );
	}

	/**
	 * Handle the scheduling and sending of appointment reminders for both the
	 * contact and admins when an appointment is cancelled
	 *
	 * @param Appointment $appointment
	 */
	public function appointment_cancelled( $appointment ) {

		current_appointment( $appointment );

		// Cancel any upcoming reminders that aren't cancellation notifications
		$this->cancel_reminders();

		// Send cancellation notifications
		$this->send_notifications( self::CANCELLED );
	}

	/**
	 * Cancel any active reminders
	 */
	public function cancel_reminders() {

		$appointment = current_appointment();

		// Cancel notifications
		get_db( 'event_queue' )->delete( [
			'funnel_id'  => $appointment->get_id(),
			'contact_id' => $appointment->get_contact_id(),
			'event_type' => Appointment_Reminder::NOTIFICATION_TYPE,
			'status'     => Event::WAITING,
		] );
	}

	const CONTACT_EMAIL = 1;
	const CONTACT_SMS = 2;
	const ADMIN_EMAIL = 3;
	const ADMIN_SMS = 4;

	/**
	 * Schedule reminders for specific purpose
	 *
	 * @param $which       int
	 *
	 * @return void
	 */
	public function _schedule_reminders( $which ) {

		$appointment = current_appointment();
		$reminders   = $appointment->get_calendar()->get_reminders( $which );

		if ( empty( $reminders ) ) {
			return;
		}

		foreach ( $reminders as $reminder ) {

			$reminder = wp_parse_args( $reminder, [
				'number' => 0,
				'period' => 'hours'
			] );

			$start    = $appointment->get_start_date();
			$interval = \DateInterval::createFromDateString( sprintf( '%d %s', $reminder['number'], $reminder['period'] ) );
			$start->sub( $interval );

			// Don't send notifications in the past
			if ( $start->getTimestamp() < time() ) {
				continue;
			}

			$event = new Event_Queue_Item();

			$event->create( [
				'time'       => $start->getTimestamp(),
				'funnel_id'  => $appointment->get_id(),
				'step_id'    => $which,
				'contact_id' => $appointment->get_contact_id(),
				'event_type' => Appointment_Reminder::NOTIFICATION_TYPE,
				'status'     => Event::WAITING,
			] );
		}

	}

	/**
	 * Schedule any reminder emails, not notifications
	 *
	 * @param Appointment $appointment
	 */
	public function schedule_reminders() {
		## EMAIL ##

		// Contact Reminders
		$this->_schedule_reminders( self::CONTACT_EMAIL );
		// Admin Reminders
		$this->_schedule_reminders( self::ADMIN_EMAIL );

		## SMS ##

		// Contact Reminders
		$this->_schedule_reminders( self::CONTACT_SMS );
		// Admin Reminders
		$this->_schedule_reminders( self::ADMIN_SMS );
	}

	/**
	 * Send all the notifications for the appointment
	 *
	 * @param string $which
	 */
	public function send_notifications( $which ) {

		$appointment = current_appointment();

		// This one goes to the client
		send_contact_email_notification( $appointment, $which );
//		maybe_send_sms_contact_notifications( $appointment, $which ); todo

		if ( $appointment->get_calendar()->is_admin_email_notification_enabled( $which ) ) {
			send_admin_email_notification( $appointment, $which );
		}

		// Goes to admin
//		maybe_send_sms_admin_notifications( $appointment, $which ); todo
	}
}
