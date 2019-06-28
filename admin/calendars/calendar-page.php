<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use Exception;
use Groundhogg\Admin\Admin_Page;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\get_request_var;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;


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

        $calendar = get_contactdata( absint( get_request_var( 'calendar_id' ) ) );
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

//        $buffer_time = absint( $calendar->get_meta( 'buffer_time',true ) );

        $appointment = new Appointment( [
            'contact_id' => $contact->get_id(),
            'calendar_id' => $calendar->get_id(),
            'name' => sanitize_text_field( get_request_var( 'appointment_name' ) ),
            'status' => 'pending',
            'start_time' => $start,
            'end_time' => $end,
        ] );

        if ( !$appointment->exists() ) {
            wp_send_json_error( __( 'Something went wrong while creating appointment.', 'groundhogg' ) );
        }

        $appointment->update_meta( 'notes', sanitize_textarea_field( get_request_var( 'notes' ) ) );

        //add appointment inside google calendar //
        $appointment->add_in_google();

        wp_send_json_success( [ 'appointment' => $appointment->get_full_calendar_event(), 'msg' => __( 'Appointment booked successfully.', 'groundhogg' ) ] );


        // Add start and end date to contact meta
//        WPGH()->contact_meta->update_meta($contact_id, 'appointment_start', date('Y-m-d', $start)); // TODO update contact meta
//        WPGH()->contact_meta->update_meta($contact_id, 'appointment_end', date('Y-m-d', $end));     //TODO update contact meta

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

        $status = $appointment->update( [
            'start_time' => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( sanitize_text_field( get_request_var( 'start_time' ) ) ) ),
            'end_time' => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( sanitize_text_field( get_request_var( 'end_time' ) ) ) ),
        ] );

        if ( !$status ) {
            wp_send_json_error( __( 'Something went wrong while updating appointment.', 'groundhogg' ) );
        }

        // Add start and end date to contact meta
//        WPGH()->contact_meta->update_meta( $appointment->contact_id, 'appointment_start', date( 'Y-m-d', $start_time ) ); TODO CONTACT META
//        WPGH()->contact_meta->update_meta( $appointment->contact_id, 'appointment_end', date( 'Y-m-d', $end_time ) ); TODO CONTACT META

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
        if ( $this->get_current_action() === 'edit' ) {
            wp_enqueue_script( 'groundhogg-appointments-admin' );
            wp_localize_script( 'groundhogg-appointments-admin', 'GroundhoggCalendar', [
                'calendar_id' => absint( get_request_var( 'calendar' ) )
            ] );
        }

//        wp_enqueue_script('groundhogg-appointments-shortcode');
//        wp_enqueue_script('groundhogg-appointments-appointments');

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
        $name = sanitize_text_field( get_request_var( 'name' ) );
        $description = sanitize_textarea_field( get_request_var( 'description' ) );

        if ( ( !$name ) || ( !$description ) ) {
            return new \WP_Error( 'no_data', __( 'Please enter name and description of calendar.', 'groundhogg' ) );
        }

        $calendar = new Calendar( [
            'user_id' => absint( get_request_var( 'owner_id', get_current_user_id() ) ),
            'name' => $name,
            'description' => $description,
        ] );

        if ( !$calendar->exists() ) {
            return new \WP_Error( 'no_calendar', __( 'Something went wrong while creating calendar.', 'groundhogg' ) );
        }

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
        $appointment_id = absint( get_request_var( 'appointment' ) );
        if ( !$appointment_id ) {
            return new \WP_Error( 'no_appointment', __( 'Appointment not found!', 'groundhogg' ) );
        }

        $contact_id = absint( get_request_var( 'contact_id' ) );
        if ( !$contact_id ) {

            return new \WP_Error( 'no_contact', __( 'Contact with this appointment not found!', 'groundhogg' ) );
        }

        $start_time = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( get_request_var( 'start_date' ) . ' ' . get_request_var( 'start_time' ) ) );
        $end_time = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( get_request_var( 'end_date' ) . ' ' . get_request_var( 'end_time' ) ) );

        //check for times
        if ( $start_time > $end_time ) {

            return new \WP_Error( 'no_contact', __( 'End time can not be smaller then start time.', 'groundhogg' ) );
        }

        $appointment = new Appointment( $appointment_id );

        /**
         * @var $appointments_table \GroundhoggBookingCalendar\DB\Appointments;
         */
        $appointments_table = get_db( 'appointments' );

        if ( $appointments_table->appointments_exist_in_range( $start_time, $end_time , $appointment->get_id()  ) ) {
            return new \WP_Error( 'appointment_clash', __( 'You already have an appointment in this time slot.', 'groundhogg' ) );
        }

        $appointment->update_meta( 'notes', sanitize_textarea_field( get_request_var( 'notes' ) ) );

        // updates current appointment with the updated details and updates google appointment
        $status = $appointment->update( [
            'contact_id' => $contact_id,
            'name' => sanitize_text_field( get_request_var( 'appointmentname' ) ),
            'start_time' => $start_time,
            'end_time' => $end_time
        ] );


        if ( !$status ) {
            $this->add_notice( new \WP_Error( 'error', 'Something went wrong...' ) );
        } else {

            $this->add_notice( 'success', __( "Appointment updated!", 'groundhogg' ), 'success' );
        }

        return true;


        // Add start and end date to contact meta
//        WPGH()->contact_meta->update_meta($contact_id, 'appointment_start', date('Y-m-d', wpgh_convert_to_utc_0($start_time))); //TODO CONTACT META
//        WPGH()->contact_meta->update_meta($contact_id, 'appointment_end', date('Y-m-d', wpgh_convert_to_utc_0($end_time))); //TODO CONTACT META

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

        return admin_url( 'admin.php?page=gh_calendar&calendar=' . $calendar_id . '&action=edit' );
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
            case 'list':
                break;
        }

        return true;
    }

    /**
     * Update calendar availability
     */
    protected function update_availability()
    {
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

//        $num = $this->get_meta( 'max_booking_period_count' );
//        $type = $this->get_meta( 'max_booking_period_type' );

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

        // save thank you page
        $calendar->update_meta( 'redirect_link_status', absint( get_request_var( 'redirect_link_status' ) ) );
        $calendar->update_meta( 'redirect_link', esc_url( get_request_var( 'redirect_link' ) ) );

        // save styling
        $colors = [
            'main_color',
            'slots_color',
            'font_color',
        ];

        foreach ( $colors as $color ) {
            $calendar->update_meta( $color, sanitize_hex_color( get_request_var( $color ) ) );
        }

        $form_override = absint( get_request_var( 'override_form_id', 0 ) );
        $calendar->update_meta( 'override_form_id', $form_override );

        // save gcal
        $google_calendar_list = get_request_var( 'google_calendar_list', [] );
        $google_calendar_list = array_map( 'sanitize_text_field', $google_calendar_list );
        $calendar->update_meta( 'google_calendar_list', $google_calendar_list );

        $this->add_notice( 'success', _x( 'Settings updated.', 'notice', 'groundhogg' ), 'success' );
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