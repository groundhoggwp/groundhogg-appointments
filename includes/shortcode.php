<?php

namespace GroundhoggBookingCalendar;

use function Groundhogg\after_form_submit_handler;
use Groundhogg\Contact;
use Groundhogg\Form\Submission_Handler;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_post_var;
use function Groundhogg\get_request_var;
use Groundhogg\Submission;
use Groundhogg\Supports_Errors;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\html;
use function Groundhogg\isset_not_empty;
use function Groundhogg\managed_page_url;


class Shortcode extends Supports_Errors
{

    /**
     * @var array
     */
    protected $atts = [];

    /**
     * @var array
     */
    protected $booking_data = [];

    /**
     * @var Calendar
     */
    protected $calendar;

    protected function get_att( $key )
    {
        return get_array_var( $this->atts, $key );
    }

    public function __construct()
    {
        add_shortcode( 'gh_calendar', [ $this, 'shortcode' ] );

        add_action( 'wp_ajax_groundhogg_calendar_get_views', [ $this, 'get_views' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_calendar_get_views', [ $this, 'get_views' ] );


        add_action( 'wp_ajax_groundhogg_add_appointment', [ $this, 'add_appointment_ajax' ] );
        add_action( 'wp_ajax_groundhogg_add_appointment', [ $this, 'ajax_error_handler' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_add_appointment', [ $this, 'add_appointment_ajax' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_add_appointment', [ $this, 'ajax_error_handler' ] );
    }

    /**
     * Get the time slots HTML
     */
    public function get_views()
    {
        $ID = absint( get_request_var( 'calendar' ) );

        $calendar = new Calendar( $ID );

        $sections = [
            'details',
            'time_slots',
            'form'
        ];

        $views = [];

        /**
         * Get the relevant HTML for each section.
         */
        foreach ( $sections as $section ){
            ob_start();
            do_action( "groundhogg/calendar/template/$section", $calendar );
            $views[ $section ] = ob_get_clean();
        }

        $step = get_post_var( 'step', 'date' );

        $classes = [];

        switch ( $step ){
            default:
            case 'date':
                $classes[] = 'view-date';
                break;
            case 'slots':
                $classes[] = 'view-slots';
                break;
            case 'form':
                $classes[] = 'view-form';
                break;
        }

        wp_send_json_success( [ 'views' => $views, 'classes' => $classes ] );
    }

    /**
     * Create an appointment
     *
     * @param $contact Contact
     */
    public function create_appointment( $contact )
    {
        $appointment = $this->calendar->schedule_appointment( [
            'contact_id' => $contact->get_id(),
            'start_time' => absint( get_array_var( $this->booking_data, 'start_time' ) ),
            'end_time' => absint( get_array_var( $this->booking_data, 'end_time' ) ),
        ] );

        if ( ! $appointment->exists() ) {
            $this->add_error( new \WP_Error( 'failed', 'Appointment not created!' ) );
            return;
        }

        $redirect_link_status = $this->calendar->get_meta( 'redirect_link_status', true );
        $redirect_link = $this->calendar->get_meta( 'redirect_link', true );

        $success_message = $this->calendar->get_meta( 'message', true );
        $success_message = html()->e( 'div', [ 'class' => 'gh-message-wrapper gh-form-success-wrapper' ], $success_message );

        after_form_submit_handler( $appointment->get_contact() );

        if ( $redirect_link_status ) {
            wp_send_json_success( [ 'message' => $success_message, 'redirect_link' => $redirect_link ] );
        }

        wp_send_json_success( [ 'message' => $success_message ] );

    }

    /**
     * Hook into the form submission process if using a linked form
     *
     * @param $submission Submission
     * @param $contact Contact
     * @param $handler Submission_Handler
     */
    public function hook_into_submission( $submission, $contact, $handler )
    {
        // do stuff, add appt...
        $this->create_appointment( $contact );

    }

    /**
     * Validate the submission
     *
     * @return bool|\WP_Error
     */
    protected function pre_validate()
    {
        $start_time = absint( get_array_var( $this->booking_data, 'start_time' ) );
        $end_time = absint( get_array_var( $this->booking_data, 'end_time' ) );

        if ( ! $start_time || $start_time < time() || ! $end_time || $end_time < time() ){
            return new \WP_Error( 'invalid_time', 'Please select valid date and time.' );
        }

        return true;
    }

    /**
     * Add the appointment via ajax
     */
    public function add_appointment_ajax()
    {

        if ( ! wp_verify_nonce( get_request_var( '_ghnonce' ), 'groundhogg_frontend' ) ) {
            $this->add_error( new \WP_Error( 'oops', 'invalid nonce' ) );
        }

        $this->booking_data = get_request_var( 'booking_data' );

        $this->calendar = new Calendar( absint( get_array_var( $this->booking_data, 'calendar_id' ) ) );

        if ( !$this->calendar->exists() ) {
            wp_send_json_error( __( 'Calendar not found!', 'groundhogg-calendar' ) );
        }

        $validated = $this->pre_validate();

        if ( ! $validated || is_wp_error( $validated ) ) {
            $this->add_error( $validated );
            return;
        };

        //reschedule appointment
        if ( isset_not_empty( $this->booking_data, 'reschedule' ) ) {
            $this->reschedule_appointment();

        // Manage form submission for adding appointment
        } else {
            $this->add_appointment();
        }

    }

    /**
     * Add a new appoinment
     */
    protected function add_appointment()
    {
        // IF HAS A LINKED FORM
        if ( $this->calendar->has_linked_form() ) {

            // ADD SUBMISSION HANDLER
            add_action( 'groundhogg/form/submission_handler/after', [ $this, 'hook_into_submission' ], 10, 3 );

            // PROCESS FORM
            if ( is_user_logged_in() ) {
                do_action( 'wp_ajax_groundhogg_ajax_form_submit' );
            } else {
                do_action( 'wp_ajax_nopriv_groundhogg_ajax_form_submit' );
            }

        } else {
            // PROCESS DEFAULT FORM

            $email = sanitize_email( get_request_var( 'email' ) );

            if ( ! is_email( $email ) ) {
                $this->add_error( new \WP_Error( 'invalid_email', __( 'Please enter valid email.', 'groundhogg-calendar' ) ) );
                return;
            }

            $args = [
                'email' => $email,
                'first_name' => sanitize_text_field( get_request_var( 'first_name' ) ),
                'last_name' => sanitize_text_field( get_request_var( 'last_name' ) ),
            ];

            // get contact by email
            $contact = get_contactdata( $args[ 'email' ] );

            if ( ! $contact ) {
                $contact = new Contact( $args );
            } else {
                $contact->update( $args );
            }

            $contact->update_meta( 'primary_phone', sanitize_text_field( get_request_var( 'phone' ) ) );

            after_form_submit_handler( $contact );

            $this->create_appointment( $contact );
        }
    }

    /**
     * Reschedule an appointment if click a reschedule link
     */
    protected function reschedule_appointment()
    {
        $appointment_id = absint( get_array_var( $this->booking_data, 'reschedule' ) );

        $appointment = new Appointment( $appointment_id );

        if ( ! $appointment->exists() ) {
            $this->add_error( new \WP_Error( 'no_appointment', __( 'Appointment not found!', 'groundhogg-calendar' ) ) );
            return;
        }

        $status = $appointment->reschedule( [
            'start_time' => absint( get_array_var( $this->booking_data, 'start_time' ) ),
            'end_time' => absint( get_array_var( $this->booking_data, 'end_time' ) ),
        ] );

        if ( ! $status ) {
            $this->add_error( new \WP_Error( 'error', __( 'could not reschedule', 'groundhogg-calendar' ) ) );
            return;
        }

        $pretty_date = $appointment->get_pretty_start_time( true );

        $success_message = sprintf( __( 'Your appointment has been rescheduled to %s!', 'groundhogg-calendar' ), $pretty_date );
        $success_message = html()->e( 'div', [ 'class' => 'gh-message-wrapper gh-form-success-wrapper' ], $success_message );
        wp_send_json_success( [ 'message' => $success_message ] );
    }

    /**
     * Main shortcode function
     * Accepts shortcode attributes and returns a string of HTML
     *
     * @param $atts array shortcode attributes
     * @return string
     */
    public function shortcode( $atts )
    {
        $atts = shortcode_atts( [
            'id'            => 0,
            'reschedule'    => 0,
        ], $atts );

        $id = get_array_var( $atts, 'id', get_array_var( $atts, 'calendar_id' ) );

        wp_enqueue_script( 'fullframe' );

        $url = managed_page_url( sprintf( 'calendar/%d/', $id ) );

	    if ( get_array_var( $atts , 'reschedule' ) ) {

		    $url = wp_nonce_url( add_query_arg( 'reschedule', absint( get_array_var( $atts,'reschedule' ) ), $url ), 'appointment_reschedule' );
	    }

        return html()->wrap( '', 'iframe', [ 'src' => $url, 'width' => '100%' ] );
    }

    /**
     * Handle any errors during the ajax submission process
     */
    public function ajax_error_handler()
    {
        if ( $this->has_errors() ) {
            wp_send_json_error( [ 'errors' => $this->get_errors(), 'html' => $this->print_errors() ] );
        }
    }

    /**
     * Outputs errors from the ajax process
     *
     * @param bool $return
     * @return bool|string
     */
    protected function print_errors( $return = true )
    {
        if ( $this->has_errors() ) {

            $errors = $this->get_errors();
            $err_html = "";

            foreach ( $errors as $error ) {
                $err_html .= sprintf( '<li id="%s">%s</li>', $error->get_error_code(), $error->get_error_message() );
            }

            $err_html = sprintf( "<ul class='gh-form-errors'>%s</ul>", $err_html );
            $err_html = sprintf( "<div class='gh-message-wrapper gh-form-errors-wrapper'>%s</div>", $err_html );

            if ( $return ) {
                return $err_html;
            }

            echo $return;

            return true;
        }

        return false;
    }

}
