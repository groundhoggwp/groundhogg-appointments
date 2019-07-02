<?php

namespace GroundhoggBookingCalendar;

use function Groundhogg\get_array_var;
use function Groundhogg\get_request_var;
use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\html;


class Shortcode
{
    protected $atts = [];

    protected function get_att( $key )
    {
        return get_array_var( $this->atts, $key );
    }

    public function __construct()
    {
        add_shortcode( 'gh_calendar', [ $this, 'gh_calendar_shortcode' ] );
        add_action( 'wp_ajax_groundhogg_get_slots', [ $this, 'get_slots' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_get_slots', [ $this, 'get_slots' ] );
    }

    public function get_slots()
    {

//        if ( ! current_user_can( 'add_appointment' ) ) {
//            wp_send_json_error();
//        }

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
}

