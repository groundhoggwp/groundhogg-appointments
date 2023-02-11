<?php

namespace GroundhoggBookingCalendar\Classes;

use Groundhogg\Contact;
use Groundhogg\Email;
use Groundhogg\Event_Process;
use GroundhoggBookingCalendar\Plugin;
use GroundhoggSMS\Classes\SMS;
use function GroundhoggBookingCalendar\is_sms_plugin_active;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMS_Reminder implements Event_Process {

	public $ID;

	/**
	 * @var SMS
	 */
	public $sms;

	/**
	 * @var Appointment
	 */
	public $appointment;


	/**
	 * @deprecated use SCHEDULED instead
	 */
	public const BOOKED = 'appointment_booked';

	/**
	 * @deprecated use SCHEDULED instead
	 */
	public const APPROVED = 'appointment_approved';
	public const SCHEDULED = 'appointment_scheduled';
	public const RESCHEDULED = 'appointment_rescheduled';
	public const CANCELLED = 'appointment_cancelled';

	public const NOTIFICATION_TYPE = 6;

	/**
	 * Reminder constructor.
	 *
	 * @param int $sms_id
	 * @param int $appointment_id
	 */
	public function __construct( $appointment_id, $sms_id ) {

		if ( ! is_sms_plugin_active() ){
			return;
		}

		$this->sms         = new SMS( $sms_id );
		$this->appointment = new Appointment( $appointment_id );
	}

	public function get_funnel_title() {
		return sprintf( __( 'SMS Reminder: %s', 'groundhogg-calendar' ), $this->appointment->get_name() );
	}

	public function get_step_title() {
		if ( ! is_sms_plugin_active() ){
			return 'SMS addon is not active';
		}

		return $this->sms->get_title();
	}

	public function run( $contact, $event ) {

		if ( ! is_sms_plugin_active() ){
			return new \WP_Error( 'sms_inactive', 'SMS addon is not active' );
		}

		Plugin::$instance->replacements->set_appointment( $this->appointment );

		do_action( 'groundhogg/calendar/sms_reminder/run/before', $this, $contact, $event );
		$result = $this->sms->send( $contact, $event );
		do_action( 'groundhogg/calendar/sms_reminder/run/after', $this, $contact, $event );

		Plugin::$instance->replacements->clear();

		return $result;
	}

	public function can_run() {
		return is_sms_plugin_active();
	}
}
