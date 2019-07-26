<?php

namespace GroundhoggBookingCalendar\Classes;


use Groundhogg\Base_Object_With_Meta;
use function Groundhogg\encrypt;
use Groundhogg\Event;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\DB\Calendar_Meta;
use \Google_Service_Calendar_Event;
use \Google_Service_Calendar;
use \Exception;
use function GroundhoggBookingCalendar\send_reminder_notification;

class Appointment extends Base_Object_With_Meta
{
    protected function get_meta_db()
    {
        return Plugin::$instance->dbs->get_db( 'appointmentmeta' );
    }

    protected function post_setup()
    {
        // TODO: Implement post_setup() method.
    }

    protected function get_db()
    {
        return Plugin::$instance->dbs->get_db( 'appointments' );
    }

    protected function get_object_type()
    {
        return 'appointment';
    }

    /**
     * Returns appointment id
     * @return int
     */
    public function get_id()
    {
        return absint( $this->ID );
    }

    /**
     * Return contact id
     * @return int
     */
    public function get_contact_id()
    {
        return absint( $this->contact_id );
    }

    /**
     * @return int
     */
    public function get_owner_id()
    {
        return $this->get_calendar()->get_user_id();
    }

    /**
     * Return calendar id
     * @return int
     */
    public function get_calendar_id()
    {
        return absint( $this->calendar_id );
    }

    protected $calendar = null;

    /**
     * @return Calendar
     */
    public function get_calendar()
    {
        if ( $this->calendar ) {
            return $this->calendar;
        }

        $this->calendar = new Calendar( $this->get_calendar_id() );
        return $this->calendar;
    }

    /**
     * Return name of appointment
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }


    public function get_status()
    {
        return $this->status;
    }

    /**
     * Return start time of appointment
     * @return int
     */
    public function get_start_time()
    {
        return absint( $this->start_time );
    }


    /**
     * Return end time of appointment
     * @return int
     */
    public function get_end_time()
    {
        return absint( $this->end_time );
    }

    /**
     * Update google as well.
     *
     * @param array $data
     * @return bool
     */
    public function update( $data = [] )
    {
        $status = parent::update( $data );

        if ( !$status ) {
            return false;
        }

        if ( $this->get_calendar()->google_enabled() ) {
            $google_status = $this->update_in_google();
            if ( !$google_status ) {
                return false;
            }
        }
        return true;
    }


    protected function update_in_google()
    {

        $access_token = $this->get_calendar()->get_access_token();
        $google_calendar_id = $this->get_calendar()->get_google_calendar_id();
        if ( $access_token && $google_calendar_id ) {

//             create google client
            $client = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->get_google_client_form_access_token( $this->get_calendar_id() );

            $service = new Google_Service_Calendar( $client );
            if ( \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->is_valid_calendar( $this->get_calendar_id(), $google_calendar_id, $service ) ) {

                $contact = get_contactdata( $this->get_contact_id() );
                $google_appointment_id = $this->get_google_appointment_id();
                $event = new Google_Service_Calendar_Event( array(
                    'id' => $google_appointment_id,
                    'summary' => $this->get_name(),
                    'description' => $this->get_meta( 'note', true ),
                    'start' => [ 'dateTime' => date( 'Y-m-d\TH:i:s', $this->get_start_time() ) . 'Z' ], //Date and time in UTC+0 and convert google required format.
                    'end' => [ 'dateTime' => date( 'Y-m-d\TH:i:s', $this->get_end_time() ) . 'Z' ],
                    'attendees' => array(
                        array( 'email' => $contact->get_email() ),
                    ),
                ) );

                try {
                    $updatedEvent = $service->events->update( $google_calendar_id, $google_appointment_id, $event );
                } catch ( \Exception $exception ) {
                    return false;
                }
            }
        }
        return true;
    }


    public function add_in_google()
    {

        $access_token = $this->get_calendar()->get_access_token();
        $google_calendar_id = $this->get_calendar()->get_google_calendar_id();
        if ( $access_token && $google_calendar_id ) {

            $client = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->get_google_client_form_access_token( $this->get_calendar_id() );
            $service = new Google_Service_Calendar( $client );
            if ( \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->is_valid_calendar( $this->get_calendar_id(), $google_calendar_id, $service ) ) {

                $contact = get_contactdata( $this->get_contact_id() );
                $event = new Google_Service_Calendar_Event( array(
                    'id' => $this->get_google_appointment_id(),
                    'summary' => $this->get_name(),
                    'description' => $this->get_meta( 'note' ),
                    'start' => [ 'dateTime' => date( 'Y-m-d\TH:i:s', $this->get_start_time() ) . 'Z' ],
                    'end' => [ 'dateTime' => date( 'Y-m-d\TH:i:s', $this->get_end_time() ) . 'Z' ],
                    'attendees' => [
                        [ 'email' => $contact->get_email() ],
                    ],
                ) );
                $event = $service->events->insert( $google_calendar_id, $event );
            }

        }
    }

    /**
     * create appointment id for google events.
     *
     * @return string
     */
    protected function get_google_appointment_id()
    {
        return 'ghcalendarcid' . $this->get_calendar_id() . 'aid' . $this->get_id();
    }


    /**
     * Delete appointment.
     *  Sends cancel email
     *  Deletes appointment from the google calendar
     *  Cancels all the pending events for the appointment
     *
     * @return bool
     */
    public function delete()
    {
        $this->cancel();

        $status = $this->get_db()->delete( $this->get_id() );

        if ( !$status ) {
            return $status;
        }

        if ( $this->get_calendar()->google_enabled() ) {
            $google_status = $this->delete_in_google();
            if ( !$google_status ) {
                return false;
            }
        }
        return true;
    }


    /**
     * Delete Appointment from google calendar if it exist.
     *
     */
    public function delete_in_google()
    {

        $access_token = $this->get_calendar()->get_access_token();
        $google_calendar_id = $this->get_calendar()->get_google_calendar_id();
        if ( $access_token && $google_calendar_id ) {

            $client = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->get_google_client_form_access_token( $this->get_calendar_id() );
            $service = new Google_Service_Calendar( $client );
            try {
                $service->events->delete( $google_calendar_id, $this->get_google_appointment_id() );
            } catch ( Exception $e ) {
                return false;
            }
        }

        return true;
    }


    public function get_full_calendar_event()
    {

        if ( $this->get_status() === 'cancelled' ) {
            $color = '#dc3545';
        } else if ( $this->get_status() == 'approved' ) {
            $color = '#28a745';
        } else {
            $color = '#0073aa';
        }

        return [
            'id' => $this->get_id(),
            'title' => $this->get_name(),
            'start' => Plugin::$instance->utils->date_time->convert_to_local_time( (int) $this->get_start_time() ) * 1000,
            'end' => Plugin::$instance->utils->date_time->convert_to_local_time( (int) $this->get_end_time() ) * 1000,
            'constraint' => 'businessHours',
            'editable' => true,
            'allDay' => false,
            'color' => $color,
            'url' => admin_url( 'admin.php?page=gh_calendar&action=edit_appointment&appointment=' . $this->get_id() )
        ];

    }

    /**
     *
     * Book the appointment
     * Schedules all the reminder emails....
     */
    public function book()
    {
        // EMAIL TIME...
        do_action( 'groundhogg/calendar/appointment/book/before' );
        $this->schedule_reminders( Reminder::BOOKED );
        do_action( 'groundhogg/calendar/appointment/book/after' );
    }

    /**
     * Reschedule Appointment
     *
     * @param $args
     * @return bool
     */
    public function reschedule( $args )
    {
        // update appointment
        $args = wp_parse_args( $args, [
            'contact_id' => $this->get_contact_id(),
            'calendar_id' => $this->get_calendar_id(),
            'name' => $this->get_name(),
            'status' => 'pending',
            'start_time' => $this->get_start_time(),
            'end_time' => $this->get_end_time(),
            'notes' => $this->get_meta( 'notes', true )
        ] );

        $note = $args[ 'notes' ];
        unset( $args[ 'notes' ] );

        if ( $note ) {
            $this->update_meta( 'notes', $note );
        }
        $status = $this->update( $args );
        if ( !$status ) {
            return false;
        }

        //cancel events form the event queue
        $this->cancel_reminders();

        // Schedule Appointment Booked Email...
        do_action( 'groundhogg/calendar/appointment/reschedule/before' );
        $this->schedule_reminders( Reminder::RESCHEDULED );
        do_action( 'groundhogg/calendar/appointment/reschedule/after' );
        do_action( 'groundhogg/calendar/appointment/reschedule', $this->get_id(), Reminder::RESCHEDULED );
        return true;
    }

    /**
     * Cancel appointment and send reminder of canceling event..
     *
     * @return bool
     */
    public function cancel()
    {
        $status = $this->update( [
            'status' => 'cancelled'
        ] );

        if ( !$status ) {
            return false;
        }

        $this->cancel_reminders();

        do_action( 'groundhogg/calendar/appointment/cancelled/before' );
        $this->schedule_reminders( Reminder::CANCELLED );
        do_action( 'groundhogg/calendar/appointment/cancelled/after' );

        do_action( 'groundhogg/calendar/appointment/cancelled', $this->get_id(), Reminder::CANCELLED );

        return true;

    }

    public function approve()
    {
        $status = $this->update( [
            'status' => 'approved'
        ] );

        if ( !$status ) {
            return false;
        }

        $this->cancel_reminders();

        do_action( 'groundhogg/calendar/appointment/approve/before' );
        $this->schedule_reminders( Reminder::APPROVED );
        do_action( 'groundhogg/calendar/appointment/approve/after' );

        do_action( 'groundhogg/calendar/appointment/approve', $this->get_id(), Reminder::APPROVED );
        return true;
    }


    protected function schedule_reminders( $which )
    {
        // Schedule Appointment Booked Email...
        if ( $booked_email_id = $this->get_calendar()->get_notification_emails( $which ) ) {
            send_reminder_notification( absint( $booked_email_id ), absint( $this->get_id() ), time() );
        }

        if ( !( $which === Reminder::CANCELLED ) ) {

            // Schedule Email Reminders...
            $reminders = $this->get_calendar()->get_reminder_emails();

            if (empty($reminders)){
                return;
            }

            foreach ( $reminders as $reminder ) {

                // Calc time...
                switch ( $reminder[ 'when' ] ) {
                    default:
                    case 'before':
                        $time = strtotime( sprintf( "-%d %s", $reminder[ 'number' ], $reminder[ 'period' ] ), $this->get_start_time() );
                        if ( $time > time() ) {
                            send_reminder_notification( $reminder[ 'email_id' ], $this->get_id(), $time );
                        }
                        break;
                    case 'after':
                        $time = strtotime( sprintf( "+%d %s", $reminder[ 'number' ], $reminder[ 'period' ] ), $this->get_end_time() );
                        if ( $time > time() ) {
                            send_reminder_notification( $reminder[ 'email_id' ], $this->get_id(), $time );
                        }
                        break;
                }

            }
        }
    }

    protected function cancel_reminders()
    {
        // delete all the waiting events for the appointment
        $events = get_db( 'events' )->query( [
            'funnel_id' => $this->get_id(),
            'contact_id' => $this->get_contact_id(),
            'event_type' => Reminder::NOTIFICATION_TYPE,
            'status' => 'waiting',
        ] );

        if ( !empty( $events ) ) {
            foreach ( $events as $event ) {
                $eve = new Event( absint( $event->ID ) );
                $eve->update( [ 'status' => 'cancelled', ] );
            }
        }
    }

    /**
     * Return a manage link to the appoitment.
     *
     * @param string $action
     * @return string
     */
    public function manage_link( $action = 'cancel' )
    {
        return site_url( sprintf( 'gh/appointment/%s/%s', urlencode( encrypt( $this->get_id() ) ), $action ) );
    }
}