<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Event;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Reminder;

/**
 * Created by PhpStorm.
 * User: atty
 * Date: 08-Jul-19
 * Time: 2:24 PM
 */
class Replacements
{

    /**
     * @var Appointment
     */
    protected $appointment;

    /**
     * @var Event
     */
    protected $event;

    public function __construct()
    {
        add_action( 'groundhogg/event/run/after', [ $this, 'clear' ] );
    }

    /**
     * Clear any cached appointment info.
     */
    public function clear()
    {
        unset( $this->appointment );
        unset( $this->event );
    }

    /**
     * The replacement codes...
     *
     * @return array
     */
    public function get_replacements()
    {
        return [
            [
                'code'        => 'appointment_start_time',
                'callback'    => array( $this, 'start_time' ),
                'description' => __( 'Returns the start date & time of a contact\'s appointment.', 'groundhogg' ),
            ],
            [
                'code'        => 'appointment_end_time',
                'callback'    => array( $this, 'end_time' ),
                'description' => __( 'Returns the end date & time of a contact\'s appointment.', 'groundhogg' ),
            ],
            [
                'code' => 'appointment_actions',
                'callback'    => array( $this, 'appointment_actions' ),
                'description' => __( 'Links to allow cancelling or re-scheduling appointments.', 'groundhogg' ),
            ],
            [
                'code' => 'appointment_notes',
                'callback'    => array( $this, 'appointment_notes' ),
                'description' => __( 'Any notes about the appointment.', 'groundhogg' ),
            ]
        ];
    }

    /**
     * @return bool|Appointment
     */
    protected function get_appointment()
    {
        if ( $this->appointment ) {
            return $this->appointment;
        }

        if ( $event = \Groundhogg\Plugin::$instance->event_queue->get_current_event() ) {

            $this->event = $event;

            // If is a reminder event
            if ( $event->get_event_type() === Reminder::NOTIFICATION_TYPE ) {
                $this->appointment = new Appointment( $event->get_funnel_id() );
                return $this->appointment;
            }

            // Otherwise get contacts last appointment...
            $appts = get_db( 'appointments' )->query( [ 'contact_id' => $event->get_contact_id() ] );

            if ( ! empty( $appts ) ){

                $last_booked = array_shift( $appts );
                $this->appointment = new Appointment( absint( $last_booked->ID ) );
                return $this->appointment;
            }

        }

        return false;
    }

    /**
     * @param Appointment $appointment
     */
    public function set_appointment( Appointment $appointment )
    {
        $this->appointment = $appointment;
    }

    /**
     * Get the appointment start time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function start_time( $contact_id=0 )
    {

        if ( ! $this->get_appointment() ) {
            return false;
        }

        $contact = get_contactdata( $contact_id );
        $local_time = $contact->get_local_time_in_utc_0( $this->get_appointment()->get_start_time() );
        $format = sprintf( "%s %s", get_option( 'date_format' ), get_option( 'time_format' ) );
        return date_i18n( $format, $local_time );

    }

    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function end_time( $contact_id=0 )
    {

        if ( ! $this->get_appointment() ) {
            return false;
        }

        $contact = get_contactdata( $contact_id );

        $local_time = $contact->get_local_time_in_utc_0( $this->get_appointment()->get_end_time() );
        $format = sprintf( "%s %s", get_option( 'date_format' ), get_option( 'time_format' ) );
        return date_i18n( $format, $local_time );
    }

    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function appointment_notes( $contact_id=0 )
    {

        if ( ! $this->get_appointment() ) {
            return false;
        }

        return $this->get_appointment()->get_meta( 'notes' );
    }

    /**
     * Insert appointment management links.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function appointment_actions( $contact_id=0 )
    {
        if ( ! $this->get_appointment() ) {
            return false;
        }

        $actions = [
            html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'reschedule' ) ], __( 'Reschedule' ) ),
            html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'cancel' ) ], __( 'Cancel' ) ),
        ];

        return implode( ' | ', $actions );
    }

}