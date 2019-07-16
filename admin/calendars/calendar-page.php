<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use Exception;
use Groundhogg\Admin\Admin_Page;
use Groundhogg\Email;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\get_email_templates;
use function Groundhogg\get_request_var;
use function Groundhogg\groundhogg_url;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;
use function GroundhoggBookingCalendar\in_between_inclusive;


// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Class Calendar_Page
 * @package GroundhoggBookingCalendar\Admin\Calendars
 */
class Calendar_Page extends Admin_Page
{

    protected function add_ajax_actions()
    {
        add_action( 'wp_ajax_groundhogg_get_appointments', [ $this, 'get_appointments_ajax' ] );
        add_action( 'wp_ajax_groundhogg_add_appointments', [ $this, 'add_appointment_ajax' ] );
        add_action( 'wp_ajax_groundhogg_update_appointments', [ $this, 'update_appointment_ajax' ] );
        add_action( 'wp_ajax_groundhogg_verify_google_calendar', [ $this, 'verify_code_ajax' ] );

    }

    /**
     * Process AJAX code for fetching appointments.
     */
    public function get_appointments_ajax()
    {

        if ( !current_user_can( 'add_appointment' ) ) {
            wp_send_json_error();
        }

        $ID = absint( get_request_var( 'calendar' ) );

        $calendar = new Calendar( $ID );

        if ( !$calendar->exists() ) {
            wp_send_json_error();
        }

        $date = get_request_var( 'date' );

        $slots = $calendar->get_appointment_slots( $date );

        if ( empty( $slots ) ) {
            wp_send_json_error( __( 'No slots available.', 'groundhogg' ) );
        }

        wp_send_json_success( [ 'slots' => $slots ] );
    }


    /**
     * Process AJAX call for adding appointments
     */
    public function add_appointment_ajax()
    {

        if ( !current_user_can( 'add_appointment' ) ) {
            wp_send_json_error();
        }

        $calendar = new Calendar( absint( get_request_var( 'calendar_id' ) ) );
        if ( !$calendar->exists() ) {
            wp_send_json_error( __( 'Calendar not found!', 'groundhogg' ) );
        }

        $contact = get_contactdata( absint( get_request_var( 'contact_id' ) ) );
        if ( !$contact->exists() ) {
            wp_send_json_error( __( 'Contact not found!', 'groundhogg' ) );
        }

        $start = absint( get_request_var( 'start_time' ) );
        $end = absint( get_request_var( 'end_time' ) );

        if ( !$start || !$end ) {
            wp_send_json_error( __( 'Please provide a valid date selection.', 'groundhogg' ) );
        }


         $appointment = $calendar->schedule_appointment( [
            'contact_id' => $contact->get_id(),
            'name' => sanitize_text_field( get_request_var( 'appointment_name' ) ),
            'start_time' => absint( $start ),
            'end_time' => absint( $end ),
            'notes' => sanitize_textarea_field( get_request_var( 'notes' ) )
        ] );


        if ( !$appointment->exists() ) {
            wp_send_json_error( __( 'Something went wrong while creating appointment.', 'groundhogg' ) );
        }

        wp_send_json_success( [ 'appointment' => $appointment->get_full_calendar_event(), 'msg' => __( 'Appointment booked successfully.', 'groundhogg' ) ] );
    }

    /**
     * Process AJAX code verification
     */
    public function verify_code_ajax()
    {
        $calendar_id = absint( get_request_var( 'calendar' ) );
        $calendar = new Calendar( $calendar_id );
        $auth_code = get_request_var( 'auth_code' );

        if ( !$auth_code ) {
            wp_send_json_error( __( 'Please enter validation code.' . $auth_code . '-asd', 'groundhogg' ) );
        }

        //call method to validate information
        try {

            $client = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->generate_access_token( $calendar->get_id(), trim( $auth_code ) );
        } catch ( Exception $e ) {

            wp_send_json_error( __( 'This code is expired or invalid and make sure you entered correct google clientID and Secret.', 'groundhogg' ) );
        }
        if ( is_wp_error( $client ) ) {
            wp_send_json_error( __( 'Please enter validation code.' . 'client error ', 'groundhogg' ) );
        }

        // sync all the existing appointment inside calender
        $appointments = get_db( 'appointments' )->query( [ 'calendar_id' => $calendar_id ] );

        foreach ( $appointments as $appo ) {
            $appointment = new Appointment( $appo->ID );
            $appointment->add_in_google();
        }

        wp_send_json_success( [ 'msg' => __( 'Your calendar synced successfully!', 'groundhogg' ) ] );

    }


    public function update_appointment_ajax()
    {
        if ( !current_user_can( 'edit_appointment' ) ) {
            wp_send_json_error();
        }

        // Handle update appointment
        $appointment = new Appointment( absint( get_request_var( 'id' ) ) );

        $status = $appointment->reschedule( [
            'start_time' => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( sanitize_text_field( get_request_var( 'start_time' ) ) ) ),
            'end_time' => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( sanitize_text_field( get_request_var( 'end_time' ) ) ) ),
        ] );

        if ( !$status ) {
            wp_send_json_error( __( 'Something went wrong while updating appointment.', 'groundhogg' ) );
        }

        wp_send_json_success( [ 'msg' => __( 'Your appointment updated successfully!', 'groundhogg' ) ] );

    }


    protected function add_additional_actions()
    {
        // TODO: Implement add_additional_actions() method.
    }

    public function get_slug()
    {
        return 'gh_calendar';
    }

    public function get_name()
    {
        return _x( 'Calendars', 'page_title', 'groundhogg' );
    }

    public function get_cap()
    {
        return 'view_calendar';
    }

    public function get_item_type()
    {
        return 'calendar';
    }

    public function get_priority()
    {
        return 48;
    }

    public function help()
    {
        // TODO: Implement help() method.
    }

    /**
     * enqueue editor scripts for full calendar
     */
    public function scripts()
    {
        if ( $this->get_current_action() === 'edit' || $this->get_current_action() === 'edit_appointment' ) {
            wp_enqueue_script( 'groundhogg-appointments-admin' );
            $calendar = new Calendar( absint( get_request_var( 'calendar' ) ) );

            if ( $this->get_current_action() === 'edit_appointment' ) {
                $appointment = new Appointment( absint( get_request_var( 'appointment' ) ) );
                $calendar = $appointment->get_calendar();
            }

            wp_localize_script( 'groundhogg-appointments-admin', 'GroundhoggCalendar', [
                'calendar_id' => absint( get_request_var( 'calendar' ) ),
                'start_of_week' => get_option( 'start_of_week' ),
                'max_date' => $calendar->get_max_booking_period( true ),
                'disabled_days' => $calendar->get_dates_no_slots(),
                'tab' => get_request_var( 'tab', 'view' ),
                'action' => $this->get_current_action()
            ] );
        }

        wp_enqueue_script( 'fullcalendar-moment' );
        wp_enqueue_script( 'fullcalendar-main' );

        // STYLES
        wp_enqueue_style( 'groundhogg-fullcalendar' );
        wp_enqueue_style( 'groundhogg-calender-admin' );
        wp_enqueue_style( 'jquery-ui' );
    }


    public function view()
    {
        if ( !class_exists( 'Calendars_Table' ) ) {
            include dirname( __FILE__ ) . '/calendars-table.php';
        }

        $calendars_table = new Calendars_Table();
        $this->search_form( __( 'Search Calendars', 'groundhogg' ) );
        $calendars_table->prepare_items();
        $calendars_table->display();
    }

    public function edit()
    {
        if ( !current_user_can( 'edit_calendar' ) ) {
            $this->wp_die_no_access();
        }

        include dirname( __FILE__ ) . '/edit.php';
    }

    public function add()
    {
        if ( !current_user_can( 'add_calendar' ) ) {
            $this->wp_die_no_access();
        }

        include dirname( __FILE__ ) . '/add.php';
    }

    public function edit_appointment()
    {
        if ( !current_user_can( 'view_appointment' ) ) {
            $this->wp_die_no_access();
        }
        include dirname( __FILE__ ) . '/../appointments/edit.php';
    }


    public function process_delete()
    {
        if ( !current_user_can( 'delete_calendar' ) ) {
            $this->wp_die_no_access();
        }

        $calendar = new Calendar( get_request_var( 'calendar' ) );
        if ( !$calendar->exists() ) {
            return new \WP_Error( 'failed', __( 'Operation failed Calendar not Found.', 'groundhogg' ) );
        }

        if ( $calendar->delete() ) {
            $this->add_notice( 'success', __( 'Calendar deleted successfully!' ), 'success' );
        }
        return true;
    }


    /**
     * Process add calendar and redirect to settings tab on successful calendar creation.
     *
     * @return string|\WP_Error
     */
    public function process_add()
    {

        if ( !current_user_can( 'add_calendar' ) ) {
            $this->wp_die_no_access();
        }

        $name = sanitize_text_field( get_request_var( 'name' ) );
        $description = sanitize_textarea_field( get_request_var( 'description' ) );

        if ( ( !$name ) || ( !$description ) ) {
            return new \WP_Error( 'no_data', __( 'Please enter name and description of calendar.', 'groundhogg' ) );
        }

        $owner_id = absint( get_request_var( 'owner_id', get_current_user_id() ) ) ;
        $calendar = new Calendar( [
            'user_id' => $owner_id ,
            'name' => $name,
            'description' => $description,
        ] );

        if ( !$calendar->exists() ) {
            return new \WP_Error( 'no_calendar', __( 'Something went wrong while creating calendar.', 'groundhogg' ) );
        }

        /* SET DEFAULTS */

        // max booking period in availability
        $calendar->update_meta( 'max_booking_period_count', absint( get_request_var( 'max_booking_period_count', 3 ) ) );
        $calendar->update_meta( 'max_booking_period_type', sanitize_text_field( get_request_var( 'max_booking_period_type', 'months' ) ) );


        //set default settings
        $calendar->update_meta( 'slot_hour', 1 );
        $calendar->update_meta( 'message', __('Appointment booked successfully!','groundhogg') );

        // Create default emails...
        $templates = get_email_templates();

        // Booked
        $booked = new Email( [
            'title' => $templates[ 'booked' ][ 'title' ],
            'subject' => $templates[ 'booked' ][ 'title' ],
            'content' => $templates[ 'booked' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $owner_id ,
        ] );

        $approved = new Email( [
            'title' => $templates[ 'approved' ][ 'title' ],
            'subject' => $templates[ 'approved' ][ 'title' ],
            'content' => $templates[ 'approved' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $owner_id ,
        ] );

        $cancelled = new Email( [
            'title' => $templates[ 'cancelled' ][ 'title' ],
            'subject' => $templates[ 'cancelled' ][ 'title' ],
            'content' => $templates[ 'cancelled' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $owner_id ,
        ] );

        $rescheduled = new Email( [
            'title' => $templates[ 'rescheduled' ][ 'title' ],
            'subject' => $templates[ 'rescheduled' ][ 'title' ],
            'content' => $templates[ 'rescheduled' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $owner_id ,
        ] );

        $reminder = new Email( [
            'title' => $templates[ 'reminder' ][ 'title' ],
            'subject' => $templates[ 'reminder' ][ 'title' ],
            'content' => $templates[ 'reminder' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $owner_id ,
        ] );

        $calendar->update_meta( 'emails', [
            'appointment_booked'        => $booked->get_id(),
            'appointment_approved'      => $approved->get_id(),
            'appointment_rescheduled'   => $rescheduled->get_id(),
            'appointment_cancelled'     => $cancelled->get_id(),
        ] );

        // set one hour before reminder by default

        $calendar->update_meta( 'reminders', [
            [
                'when' => 'before',
                'period' => 'hours',
                'number' => 1,
                'email_id' => $reminder->get_id()
            ]
        ] );

        $this->add_notice( 'success', __( 'New calendar created successfully!', 'groundhogg' ), 'success' );
        return admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar->get_id() . '&tab=settings' );

    }


    public function process_google_sync()
    {
        if ( !current_user_can( 'edit_appointment' ) ) {
            $this->wp_die_no_access();
        }
        $calendar = new Calendar( absint( get_request_var( 'calendar' ) ) );

        $status = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->sync( $calendar->get_id() );

        if ( is_wp_error( $status ) ) {
            return $status;
        }

        $this->add_notice( 'success', __( 'Appointments synced successfully!', 'groundhogg' ), 'success' );

        return admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar->get_id() . '&tab=view' );

    }

    /**
     * Process update appointment request post by the edit_appointment page.
     *
     * @return bool|\WP_Error
     */
    public function process_edit_appointment()
    {

        if ( !current_user_can( 'edit_appointment' ) ) {
            $this->wp_die_no_access();
        }

        $appointment_id = absint( get_request_var( 'appointment' ) );
        if ( !$appointment_id ) {
            return new \WP_Error( 'no_appointment', __( 'Appointment not found!', 'groundhogg' ) );
        }

        $appointment = new Appointment( $appointment_id );

        $contact_id = absint( get_request_var( 'contact_id' ) );

        if ( !$contact_id ) {
            return new \WP_Error( 'no_contact', __( 'Contact with this appointment not found!', 'groundhogg' ) );
        }

        if ( !( get_request_var( 'start_date' ) === get_request_var( 'end_date' ) ) ) {
            return new \WP_Error( 'different_date', __( 'Start date and end date needs to be same.', 'groundhogg' ) );
        }

        //check appointment is in working hours.....
        $availability = $appointment->get_calendar()->get_todays_available_periods( get_request_var( 'start_date' ) );
        if ( empty( $availability ) ) {
            return new \WP_Error( 'not_available', __( 'This date is not available.', 'groundhogg' ) );
        }

        $flag = false;
        foreach ( $availability as $appoi ) {
            if ( in_between_inclusive( strtotime( get_request_var( 'start_time' ) ), strtotime( $appoi[ 0 ] ), strtotime( $appoi[ 1 ] ) ) && in_between_inclusive( strtotime( get_request_var( 'end_time' ) ), strtotime( $appoi[ 0 ] ), strtotime( $appoi[ 1 ] ) ) ) {
                $flag = true;
            }
        }

        if ( !$flag ) {
            return new \WP_Error( 'not_available', __( 'Appointment is out of availability.', 'groundhogg' ) );
        }

        $start_time = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( get_request_var( 'start_date' ) . ' ' . get_request_var( 'start_time' ) ) );
        $end_time = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( get_request_var( 'end_date' ) . ' ' . get_request_var( 'end_time' ) ) );

        //check for times
        if ( $start_time > $end_time ) {
            return new \WP_Error( 'no_contact', __( 'End time can not be smaller then start time.', 'groundhogg' ) );
        }

        /**
         * @var $appointments_table \GroundhoggBookingCalendar\DB\Appointments;
         */
        $appointments_table = get_db( 'appointments' );

        if ( $appointments_table->appointments_exist_in_range( $start_time, $end_time, $appointment->get_calendar_id() ) ) {
            return new \WP_Error( 'appointment_clash', __( 'You already have an appointment in this time slot.', 'groundhogg' ) );
        }

        // updates current appointment with the updated details and updates google appointment
        $status = $appointment->reschedule( [
            'contact_id' => $contact_id,
            'name' => sanitize_text_field( get_request_var( 'appointmentname' ) ),
            'start_time' => $start_time,
            'end_time' => $end_time,
            'notes' => sanitize_textarea_field( get_request_var( 'notes' ) )
        ] );

        if ( !$status ) {
            $this->add_notice( new \WP_Error( 'error', 'Something went wrong...' ) );
        } else {
            $this->add_notice( 'success', __( "Appointment updated!", 'groundhogg' ), 'success' );
        }

        return true;
    }


    public function process_approve_appointment()
    {
        if ( !current_user_can( 'edit_appointment' ) ) {
            $this->wp_die_no_access();
        }

        $appointment_id = get_request_var( 'appointment' );
        if ( !$appointment_id ) {
            return new \WP_Error( 'no_appointment_id', __( 'Appointment ID not found', 'groundhogg' ) );
        }
        $appointment = new Appointment( $appointment_id );
        if ( !$appointment->exists() ) {
            wp_die( __( "Appointment not found!", 'groundhogg' ) );
        }

        $status = $appointment->approve();
        if ( !$status ) {
            return new \WP_Error( 'update_failed', __( 'Status not updated.', 'groundhogg' ) );
        } else {
            $this->add_notice( 'success', __( 'Appointment status changed!', 'groundhogg' ), 'success' );
        }

        return admin_url( 'admin.php?page=gh_calendar&calendar=' . $appointment->get_calendar_id() . '&action=edit' );
    }

    public function process_cancel_appointment()
    {
        if ( !current_user_can( 'edit_appointment' ) ) {
            $this->wp_die_no_access();
        }

        $appointment_id = get_request_var( 'appointment' );
        if ( !$appointment_id ) {
            return new \WP_Error( 'no_appointment_id', __( 'Appointment ID not found', 'groundhogg' ) );
        }

        $appointment = new Appointment( $appointment_id );
        if ( !$appointment->exists() ) {
            wp_die( __( "Appointment not found!", 'groundhogg' ) );
        }

        $status = $appointment->cancel();
        if ( !$status ) {
            return new \WP_Error( 'update_failed', __( 'Status not updated.', 'groundhogg' ) );
        } else {
            $this->add_notice( 'success', __( 'Appointment status changed!', 'groundhogg' ), 'success' );
        }
        return admin_url( 'admin.php?page=gh_calendar&calendar=' . $appointment->get_calendar_id() . '&action=edit' );
    }

    /**
     *  Delete appointment from database and google calendar if connected.
     *
     * @return string|\WP_Error
     */
    public function process_delete_appointment()
    {
        if ( !current_user_can( 'delete_appointment' ) ) {
            $this->wp_die_no_access();
        }

        $appointment_id = get_request_var( 'appointment' );
        if ( !$appointment_id ) {
            return new \WP_Error( 'no_appointment_id', __( 'Appointment ID not found', 'groundhogg' ) );
        }

        $appointment = new Appointment( $appointment_id );
        if ( !$appointment->exists() ) {
            wp_die( __( "Appointment not found!", 'groundhogg' ) );
        }

        $calendar_id = $appointment->get_calendar_id();

        $status = $appointment->delete();
        if ( !$status ) {
            return new \WP_Error( 'delete_failed', __( 'Something went wrong while deleting appointment.', 'groundhogg' ) );
        } else {
            $this->add_notice( 'success', __( 'Appointment deleted!', 'groundhogg' ), 'success' );
        }

        return admin_url( 'admin.php?page=gh_calendar&calendar=' . $calendar_id . '&action=edit&tab=list' );
    }

    /**
     * manage tab's post request by calling appropriate function.
     *
     * @return bool
     */
    public function process_edit()
    {
        if ( !current_user_can( 'edit_calendar' ) ) {
            $this->wp_die_no_access();
        }

        $tab = get_request_var( 'tab', 'view' );

        switch ( $tab ) {

            default:
            case 'view':
                // Update actions from View
                break;
            case 'settings':
                // Update Settings Page
                $this->update_calendar_settings();
                break;
            case 'availability':
                // Update Availability
                $this->update_availability();
                break;
            case 'emails':

                $this->update_emails();
                break;
            case 'list':
                break;
        }

        return true;
    }

    protected function update_emails()
    {

        if ( !current_user_can( 'edit_calendar' ) ) {
            $this->wp_die_no_access();
        }

        $calendar = new Calendar( get_request_var( 'calendar' ) );
        $calendar->update_meta( 'emails', [
            'appointment_booked' => absint( get_request_var( 'appointment_booked' ) ),
            'appointment_approved' => absint( get_request_var( 'appointment_approved' ) ),
            'appointment_rescheduled' => absint( get_request_var( 'appointment_rescheduled' ) ),
            'appointment_cancelled' => absint( get_request_var( 'appointment_cancelled' ) )
        ] );


        $reminders = get_request_var( 'reminders' );

        $operation = get_array_var( $reminders, 'when' );
        $number = get_array_var( $reminders, 'number' );
        $period = get_array_var( $reminders, 'period' );
        $email_id = get_array_var( $reminders, 'email_id' );

        $reminder = [];
        if ( empty( $operation ) ) {
            $calendar->update_meta( 'reminders', '' );
        } else {

            foreach ( $operation as $i => $op ) {
                $temp_reminders = [];
                $temp_reminders[ 'when' ] = $operation [ $i ];
                $temp_reminders[ 'number' ] = $number[ $i ];
                $temp_reminders[ 'period' ] = $period[ $i ];
                $temp_reminders[ 'email_id' ] = $email_id [ $i ];
                $reminder[] = $temp_reminders;
            }
            $calendar->update_meta( 'reminders', $reminder );
        }
    }


    /**
     * Update calendar availability
     */
    protected function update_availability()
    {

        if ( !current_user_can( 'edit_calendar' ) ) {
            $this->wp_die_no_access();
        }

        $calendar_id = absint( get_request_var( 'calendar' ) );

        $calendar = new Calendar( $calendar_id );

        $rules = get_request_var( 'rules' );

//        wp_send_json_error( $rules );

        $days = get_array_var( $rules, 'day' );
        $starts = get_array_var( $rules, 'start' );
        $ends = get_array_var( $rules, 'end' );

        $availability = [];

        if ( !$days ) {
            $this->add_notice( new \WP_Error( 'error', 'Please add at least one availability slot' ) );
            return;
        }

        foreach ( $days as $i => $day ) {

            $temp_rule = [];
            $temp_rule[ 'day' ] = $day;
            $temp_rule[ 'start' ] = $starts[ $i ];
            $temp_rule[ 'end' ] = $ends[ $i ];

            $availability[] = $temp_rule;

        }

        $calendar->update_meta( 'max_booking_period_count', absint( get_request_var( 'max_booking_period_count', 3 ) ) );
        $calendar->update_meta( 'max_booking_period_type', sanitize_text_field( get_request_var( 'max_booking_period_type', 'months' ) ) );

        $calendar->update_meta( 'rules', $availability );

        $this->add_notice( 'updated', __( 'Availability updated.' ) );
    }

    /**
     *  Updates the calendar settings.
     */
    protected function update_calendar_settings()
    {
        $calendar_id = absint( get_request_var( 'calendar' ) );

        $calendar = new Calendar( $calendar_id );

        $args = array(
            'user_id' => absint( get_request_var( 'owner_id', get_current_user_id() ) ),
            'name' => sanitize_text_field( get_request_var( 'name', $calendar->get_name() ) ),
            'description' => sanitize_textarea_field( get_request_var( 'description' ) ),
        );

        if ( !$calendar->update( $args ) ) {
            $this->add_notice( new \WP_Error( 'error', 'Unable to update calendar.' ) );
            return;
        }

        // Save 12 hour
        if ( get_request_var( 'time_12hour' ) ) {
            $calendar->update_meta( 'time_12hour', true );
        } else {
            $calendar->delete_meta( 'time_12hour' );
        }

        // Save appointment length
        $calendar->update_meta( 'slot_hour', absint( get_request_var( 'slot_hour', 0 ) ) );
        $calendar->update_meta( 'slot_minute', absint( get_request_var( 'slot_minute', 0 ) ) );

        // Save buffer time
        $calendar->update_meta( 'buffer_time', absint( get_request_var( 'buffer_time', 0 ) ) );

        // Save make me look busy
        $calendar->update_meta( 'busy_slot', absint( get_request_var( 'busy_slot', 0 ) ) );

        // save success message
        $calendar->update_meta( 'message', wp_kses_post( get_request_var( 'message' ) ) );

        //save default note
        $calendar->update_meta( 'default_note', sanitize_textarea_field( get_request_var('default_note'))  );

        // save thank you page
        $calendar->update_meta( 'redirect_link_status', absint( get_request_var( 'redirect_link_status' ) ) );
        $calendar->update_meta( 'redirect_link', esc_url( get_request_var( 'redirect_link' ) ) );

        $form_override = absint( get_request_var( 'override_form_id', 0 ) );
        $calendar->update_meta( 'override_form_id', $form_override );

        // save gcal
        $google_calendar_list = get_request_var( 'google_calendar_list', [] );
        $google_calendar_list = array_map( 'sanitize_text_field', $google_calendar_list );
        $calendar->update_meta( 'google_calendar_list', $google_calendar_list );

        $this->add_notice( 'success', _x( 'Settings updated.', 'notice', 'groundhogg' ), 'success' );


//          /* unused in 2.0 */
//        $calendar->update_meta( 'slot_title', sanitize_text_field( get_request_var( 'slot_title' ) ) ); // NOT USED IN 2.0
//        // save styling
//        $colors = [
//            'main_color',
//            'slots_color',
//            'font_color',
//        ];
//
//        foreach ( $colors as $color ) {
//            $calendar->update_meta( $color, sanitize_hex_color( get_request_var( $color ) ) );
//        }

    }


    public function process_access_code()
    {
        $client = \GroundhoggBookingCalendar\Plugin::$instance->google_calendar->get_basic_client();
        if ( is_wp_error( $client ) ) {
            return new \WP_Error( 'client_error', __( 'Please check your google clientId and Secret.', 'groundhogg' ) );
        }
        $authUrl = $client->createAuthUrl();
        echo "<script>window.open(\"" . $authUrl . "\",\"_self\");</script>";
        return true;
    }



}