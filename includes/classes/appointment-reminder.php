<?php

namespace GroundhoggBookingCalendar\Classes;

use Groundhogg\Contact;
use Groundhogg\Event;
use Groundhogg\Event_Process;
use function GroundhoggBookingCalendar\send_admin_email_notification;
use function GroundhoggBookingCalendar\send_contact_email_notification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Appointment_Reminder implements Event_Process {

	public $ID;

	/**
	 * @var Appointment
	 */
	public $appointment;

	const CONTACT_EMAIL = 1;
	const CONTACT_SMS = 2;
	const ADMIN_EMAIL = 3;
	const ADMIN_SMS = 4;
	const REMINDER = 'reminder';
	const ADMIN_REMINDER = 'admin_reminder';

	public const NOTIFICATION_TYPE = 5;

	/**
	 * Reminder constructor.
	 *
	 * @param $event Event
	 */
	public function __construct( $event ) {
		$this->appointment = new Appointment( $event->get_funnel_id() );
	}

	public function get_funnel_title() {
		return __( 'Appointment Reminder: %s', 'groundhogg-calendar' );
	}

	public function get_step_title() {
		return $this->appointment->get_name();
	}

	/**
	 * Process the notification
	 *
	 * @param $contact Contact
	 * @param $event   Event
	 *
	 * @return false|void
	 */
	public function run( $contact, $event ) {

		if ( ! $this->appointment->exists() ) {
			return false;
		}

		switch ( $event->get_step_id() ) {
			case self::CONTACT_EMAIL:
				send_contact_email_notification( $this->appointment, self::CONTACT_EMAIL );
				break;
			case self::CONTACT_SMS:
				break;
			case self::ADMIN_EMAIL:
				send_admin_email_notification( $this->appointment, self::ADMIN_EMAIL );
				break;
			case self::ADMIN_SMS:
				break;
		}
	}

	public function can_run() {
		return $this->appointment->is_scheduled();
	}
}
