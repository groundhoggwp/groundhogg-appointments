<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Contact;
use Groundhogg\Form\Submission_Handler;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_request_var;
use Groundhogg\Submission;
use Groundhogg\Supports_Errors;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\html;


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
        add_shortcode( 'gh_calendar', [ $this, 'gh_calendar_shortcode' ] );

        add_action( 'wp_ajax_groundhogg_get_slots', [ $this, 'get_slots' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_get_slots', [ $this, 'get_slots' ] );


        add_action( 'wp_ajax_groundhogg_add_appointment', [ $this, 'add_appointment_ajax' ] );
        add_action( 'wp_ajax_groundhogg_add_appointment', [ $this, 'ajax_error_handler' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_add_appointment', [ $this, 'add_appointment_ajax' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_add_appointment', [ $this, 'ajax_error_handler' ] );
    }

    /**
     * @param $contact Contact
     */
    public function create_appointment( $contact )
    {
        $appointment = new Appointment( [
            'contact_id' => $contact->get_id(),
            'calendar_id' => $this->calendar->get_id(),
            'name' => $this->calendar->get_name(),
            'status' => 'pending',
            'start_time' => absint( get_array_var( $this->booking_data, 'start_time' ) ),
            'end_time' => absint( get_array_var( $this->booking_data, 'end_time' ) ),
        ] );

        if ( !$appointment->exists() ) {
            $this->add_error( new \WP_Error( 'failed', 'Appointment not created!' ) );
            return;
        }

        $appointment->update_meta( 'notes', sanitize_textarea_field( get_request_var( 'notes' ) ) );

        //add appointment inside google calendar //
        $appointment->add_in_google();

        $redirect_link_status = $this->calendar->get_meta( 'redirect_link_status', true );
        $redirect_link = $this->calendar->get_meta( 'redirect_link', true );
        if ( $redirect_link_status ) {
            wp_send_json_success( [ 'message' => __( $this->calendar->get_meta( 'message', true ), 'groundhogg' ), 'redirect_link' => $redirect_link ] );
        } else {
            wp_send_json_success( [ 'message' => __( $this->calendar->get_meta( 'message', true ), 'groundhogg' ) ] );
        }
    }

    /**
     * @param $submission Submission
     * @param $contact Contact
     * @param $handler Submission_Handler
     */
    public function hook_into_submission( $submission, $contact, $handler )
    {
        // do stuff, add appt...
        $this->create_appointment( $contact );

    }

    protected function pre_validate()
    {
        if ( ( !get_array_var( $this->booking_data, 'start_time' ) ) || ( !get_array_var( $this->booking_data, 'end_time' ) ) ) {
            return new \WP_Error( 'invalid_time', 'Please select valid date and time.' );
        }
        return true;
    }

    public function add_appointment_ajax()
    {

        if ( !wp_verify_nonce( get_request_var( '_ghnonce' ), 'groundhogg_frontend' ) ) {
            $this->add_error( new \WP_Error( 'oops', 'invalid nonce' ) );
        }

        $this->booking_data = get_request_var( 'booking_data' );

        $this->calendar = new Calendar( absint( get_array_var( $this->booking_data, 'calendar_id' ) ) );

        if ( !$this->calendar->exists() ) {
            wp_send_json_error( __( 'Calendar not found!', 'groundhogg' ) );
        }

        $validated = $this->pre_validate();

        if ( !$validated || is_wp_error( $validated ) ) {
            $this->add_error( $validated );
            return;
        };

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

            $email = sanitize_email( stripslashes( $_POST[ 'email' ] ) );
            if ( !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                $this->add_error( new \WP_Error( 'invalid_email', 'Please enter valid email.' ) );
                return;
            }
            // get contact by email

            $contact = get_contactdata( $email );
            if ( !$contact ) {
                $contact = new Contact([
                    'email' => $email,
                    'first_name' => sanitize_text_field(get_request_var('first_name')),
                    'last_name' => sanitize_text_field(get_request_var('last_name')),
                ]);
                $contact->update_meta('primary_phone', sanitize_text_field(get_request_var( 'phone' ))) ;
            }
            $this->create_appointment($contact);

        }

        // Add start and end date to contact meta
//        WPGH()->contact_meta->update_meta($contact_id, 'appointment_start', date('Y-m-d', $start)); // TODO update contact meta
//        WPGH()->contact_meta->update_meta($contact_id, 'appointment_end', date('Y-m-d', $end));     //TODO update contact meta
    }

    public function get_slots()
    {

        $ID = absint( get_request_var( 'calendar' ) );

        $calendar = new Calendar( $ID );

        if ( !$calendar->exists() ) {
            wp_send_json_error();
        }

        $date = get_request_var( 'date' );

        $slots = $calendar->get_appointment_slots( $date, get_request_var( 'timeZone' ) );

        if ( empty( $slots ) ) {
            wp_send_json_error( __( 'No slots available.', 'groundhogg' ) );
        }

        wp_send_json_success( [ 'slots' => $slots ] );
    }


    /**
     * Main shortcode function
     * Accepts shortcode attributes and returns a string of HTML
     *
     * @param $atts array shortcode attributes
     * @return string
     */
    public function gh_calendar_shortcode( $atts )
    {
        $atts = shortcode_atts( [
            'id' => 0
        ], $atts );

        wp_enqueue_script( 'fullframe' );

        return html()->wrap( '', 'iframe', [ 'src' => site_url( 'gh/calendar/' . $atts[ 'id' ] ), 'width' => '100%' ] );
    }


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

    public function ajax_error_handler()
    {
        if ( $this->has_errors() ) {
            wp_send_json_error( [ 'errors' => $this->get_errors(), 'html' => $this->print_errors() ] );
        }
    }
}
