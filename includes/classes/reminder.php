<?php

namespace GroundhoggBookingCalendar\Classes;

use Groundhogg\Contact;
use Groundhogg\Email;
use Groundhogg\Event_Process;

if ( ! defined( 'ABSPATH' ) ) exit;

class Reminder implements Event_Process
{

    public $ID;

    /**
     * @var Email
     */
    public $email;

    /**
     * @var Appointment
     */
    public $appointment;

    const BOOKED        = 'appointment_booked';
    const APPROVED      = 'appointment_approved';
    const RESCHEDULED   = 'appointment_rescheduled';
    const CANCELLED     = 'appointment_cancelled';

    const NOTIFICATION_TYPE = 5;

    /**
     * Reminder constructor.
     * @param int $email_id
     * @param int $appointment_id
     */
    public function __construct( $appointment_id, $email_id )
    {
        $this->email = new Email( $email_id );
        $this->appointment = new Appointment( $appointment_id );
    }

    public function get_funnel_title()
    {
        return sprintf( __( 'Appointment Reminder: %s', 'groundhogg-calendar' ), $this->appointment->get_name() );
    }

    public function get_step_title()
    {
        return $this->email->get_title();
    }

    public function run( $contact, $event )
    {
        do_action( 'groundhogg/calendar/reminder/run/before', $this, $contact, $event );
        $result = $this->email->send( $contact, $event );
        do_action( 'groundhogg/calendar/reminder/run/after', $this, $contact, $event );
        return $result;
    }

    public function can_run()
    {
        return true;
    }
}