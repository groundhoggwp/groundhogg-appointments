<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Event;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_date_time_format;
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
                'code' => 'appointment_start_time',
                'callback' => array( $this, 'start_time' ),
                'description' => __( 'Returns the start date & time of a contact\'s appointment.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'appointment_start_time_admin',
                'callback' => array( $this, 'start_time_admin' ),
                'description' => __( 'Returns the start date & time of a contact\'s appointment in the admin\'s timezone.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'appointment_end_time',
                'callback' => array( $this, 'end_time' ),
                'description' => __( 'Returns the end date & time of a contact\'s appointment.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'appointment_end_time_admin',
                'callback' => array( $this, 'end_time_admin' ),
                'description' => __( 'Returns the end date & time of a contact\'s appointment in the admin\'s timezone.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'appointment_actions',
                'callback' => array( $this, 'appointment_actions' ),
                'description' => __( 'Links to allow cancelling or re-scheduling appointments.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'appointment_notes',
                'callback' => array( $this, 'appointment_notes' ),
                'description' => __( 'Any notes about the appointment.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'zoom_meeting_details',
                'callback' => array( $this, 'zoom_meeting_details' ),
                'description' => __( 'Detail Description about zoom meeting.( needs zoom enabled and synced )', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'calendar_owner_first_name',
                'callback' => array( $this, 'calendar_owner_first_name' ),
                'description' => __( 'First name of calendar owner.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'calendar_owner_last_name',
                'callback' => array( $this, 'calendar_owner_last_name' ),
                'description' => __( 'Last name of calendar owner.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'calendar_owner_email',
                'callback' => array( $this, 'calendar_owner_email' ),
                'description' => __( 'Email address of calendar owner.', 'groundhogg-calendar' ),
            ],
            [
                'code' => 'calendar_owner_phone',
                'callback' => array( $this, 'calendar_owner_phone' ),
                'description' => __( 'Phone number of calendar owner.', 'groundhogg-calendar' ),
            ]

        ];
    }

    /**
     * @return bool|Appointment
     */
    protected function get_appointment()
    {
        if ( isset($this->appointment ) ) {
            return $this->appointment;
        }

        if ( $event = \Groundhogg\Plugin::$instance->event_queue->get_current_event() ) {

            $this->event = $event;

            // If is a reminder event
            if ( $event->get_event_type() === Reminder::NOTIFICATION_TYPE ||  $event->get_event_type() === SMS_Reminder::NOTIFICATION_TYPE ) {
                $this->appointment = new Appointment( $event->get_funnel_id() );
                return $this->appointment;
            }

            // Otherwise get contacts last appointment...
            $appts = get_db( 'appointments' )->query( [ 'contact_id' => $event->get_contact_id() ] );

            if ( !empty( $appts ) ) {

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
    public function start_time( $contact_id = 0 )
    {

        if ( !$this->get_appointment() ) {
            return false;
        }

        $contact = get_contactdata( $contact_id );

        $local_time = $contact->get_local_time( $this->get_appointment()->get_start_time() );

        $format = get_date_time_format();

        return date_i18n( $format, $local_time );
    }

    /**
     * Get the appointment start time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function start_time_admin( $contact_id = 0 )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        $local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_local_time( $this->get_appointment()->get_start_time() );
        $format = get_date_time_format();

        return date_i18n( $format, $local_time );
    }

    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function end_time( $contact_id = 0 )
    {

        if ( !$this->get_appointment() ) {
            return false;
        }

        $contact = get_contactdata( $contact_id );
        $local_time = $contact->get_local_time( $this->get_appointment()->get_end_time() );
        $format = get_date_time_format();


        return date_i18n( $format, $local_time );
    }

    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function end_time_admin( $contact_id = 0 )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        $local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_local_time( $this->get_appointment()->get_end_time() );
        $format = get_date_time_format();

        return date_i18n( $format, $local_time );
    }

    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function appointment_notes( $contact_id = 0 )
    {

        if ( !$this->get_appointment() ) {
            return false;
        }

        if ( !$this->get_appointment()->get_meta( 'notes' ) ) {
            return __( 'There is no additional note with this appointment.', 'groundhogg-calendar' );
        }

        return wpautop( $this->get_appointment()->get_meta( 'notes' ) );
    }

    /**
     * Insert appointment management links.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function appointment_actions( $contact_id = 0 )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        $actions = [
            html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'reschedule' ) ], __( 'Reschedule' ) ),
            html()->e( 'a', [ 'href' => $this->get_appointment()->manage_link( 'cancel' ) ], __( 'Cancel' ) ),
        ];

        return implode( ' | ', $actions );
    }


           /**
         * fetch Zoom meting description from zoom
         *
         * @return bool|string
         */
        public function zoom_meeting_details( )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        return wpautop( $this->get_appointment()->get_zoom_meeting_detail() );

    }


    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function calendar_owner_first_name( $contact_id = 0 )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        $owner_id = $this->get_appointment()->get_owner_id();

        $user = get_user_by( 'ID' , $owner_id );
        if ( !$user ) {
            //return admin details
            $user = get_user_by( 'email', get_bloginfo( 'admin_email' ) );
        }

        return $user->first_name;
    }

    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function calendar_owner_last_name( $contact_id = 0 )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        $owner_id = $this->get_appointment()->get_owner_id();

        $user = get_user_by( 'ID' , $owner_id );
        if ( !$user ) {
            //return admin details
            $user = get_user_by( 'email', get_bloginfo( 'admin_email' ) );
        }

        return $user->last_name;
    }

    /**
     * Get the appointment end time.
     *
     * @param int $contact_id
     * @return bool|string
     */
    public function calendar_owner_email( $contact_id = 0 )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        $owner_id = $this->get_appointment()->get_owner_id();

        $user = get_user_by( 'ID' , $owner_id );
        if ( !$user ) {
            //return admin details
            $user = get_user_by( 'email', get_bloginfo( 'admin_email' ) );
        }

        return $user->user_email;
    }

    public function calendar_owner_phone( $contact_id = 0 )
    {
        if ( !$this->get_appointment() ) {
            return false;
        }

        $owner_id = $this->get_appointment()->get_owner_id();

        $user = get_user_by( 'ID' , $owner_id );
        if ( !$user || !$user->phone ) {
            //return admin details
            return \Groundhogg\Plugin::instance()->replacements->replacement_business_phone();
        }

        return $user->phone;
    }



}