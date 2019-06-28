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
        add_shortcode( 'gh_calendar', array( $this, 'gh_calendar_shortcode' ) );
        add_action( 'wp_ajax_groundhogg_get_appointment_client', [ $this, 'get_appointment_client_ajax' ] );
        add_action( 'wp_ajax_nopriv_groundhogg_get_appointment_client', [ $this, 'get_appointment_client_ajax' ] );


    }

    public function get_appointment_client_ajax()
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
        wp_enqueue_script( 'groundhogg-appointments-shortcode' );

        wp_enqueue_style( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'groundhogg-calendar-frontend' );
        wp_enqueue_style( 'groundhogg-form' );
        wp_enqueue_style( 'groundhogg-loader' );

        $start_of_week = get_option( 'start_of_week' );

        $args = shortcode_atts( array(
            'calendar_id' => 0,
            'appointment_name' => __( 'New Client Appointment', 'groundhogg' )
        ), $atts );

        // get calendar id  form short code

        $calendar = new Calendar( absint( $args[ 'calendar_id' ] ) );

        if ( !$calendar->exists() ) {
            return sprintf( '<p>%s</p>', __( 'The given calendar ID does not exist.', 'groundhogg' ) );
        }


        $title = $calendar->get_meta( 'slot_title', true );
        if ( $title === null ) {
            $title = __( 'Time Slot', 'groundhogg' );
        }
        $appointment_name = sanitize_text_field( stripslashes( $args[ 'appointment_name' ] ) ); // get name for clients

        $main_color = $calendar->get_meta( 'main_color', true );
        if ( !$main_color ) {
            $main_color = '#f7f7f7';
        }

        $font_color = $calendar->get_meta( 'font_color', true ) ? $calendar->get_meta( 'font_color', true ) : '#292929';
        if ( !$font_color ) {
            $font_color = '#292929';
        }

        $slots_color = $calendar->get_meta( 'slots_color', true ) ? $calendar->get_meta( 'slots_color', true ) : '#29a2d9';

        ob_start();

        ?>
        <script>
            (function ($) {
                // WRITE THE VALIDATION SCRIPT.
                function isNumber(evt) {
                    var iKeyCode = (evt.which) ? evt.which : evt.keyCode
                    if (iKeyCode != 43 && iKeyCode > 31 && (iKeyCode < 48 || iKeyCode > 57))
                        return false;
                    return true;
                }

                $(function () {
                    $('#appt-calendar').datepicker({
                        firstDay: <?php echo $start_of_week; ?>,
                        minDate: 0,
                        maxDate: '<?php echo $calendar->get_max_booking_period(); ?>',
                        changeMonth: false,
                        changeYear: false,
                        dateFormat: 'yy-mm-dd'

                    });
                });
            })(jQuery);
        </script>
        <div class="calendar-form-wrapper">
            <form class="gh-calendar-form" method="post">

                <input type="hidden" name="calendar_id" id="calendar_id" value="<?php echo $calendar->get_id(); ?>"/>
                <input type="hidden" id="appointment_name" value="<?php echo $appointment_name; ?>"/>

                <div class="gh-form">
                    <div class="gh-form-row clearfix">
                        <div class="gh-form-column col-2-of-3">
                            <div class="groundhogg-calendar">
                                <div id="appt-calendar" style="width: 100%"></div>
                            </div>
                        </div>
                        <div class="gh-form-column col-1-of-3">
                            <div style="text-align: center;margin-top: 15px;" id="spinner">
                                <span class="gh-loader" style="font-size:10px;margin:20px;float: none; visibility: visible"></span>
                            </div>
                            <div id="time-slots" class="select-time hidden">
                                <p class="time-slot-select-text"><b><?php _e( $title, 'groundhogg' ) ?></b></p>
                                <hr class="time-slot-divider"/>

                                <div id="select_time"></div>
                                <hr class="time-slot-divider"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="appointment-errors" class="gh-message-wrapper gh-form-errors-wrapper hidden"></div>
                <div id="details-form" class="details-form gh-form-wrapper ">
                    <?php
                    //                    $override_form = absint( $calendar->get_meta( 'override_form_id' ) );
                    //                    if ( $override_form ) {
                    //                        echo do_shortcode( sprintf( '[gh_form id="%d"]', $override_form ) );
                    //                    } else {
                    $this->default_form();
                    //                    }
                    ?>
                </div>
            </form>
        </div>

        <?php
        $content = ob_get_clean();
        return $content;
    }

    /**
     * output default form
     */
    protected function default_form()
    {

        $contact = \Groundhogg\Plugin::$instance->tracking->get_current_contact();

        ?>
        <div class="gh-form">
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-2">
                    <div class="gh-form-field">

                        <?php
                        echo html()->input( [
                            'type' => 'text',
                            'name' => 'first_name',
                            'id' => 'first_name',
                            'placeholder' => _e( 'First Name' ),
                            'value' => $contact->get_first_name() ? $contact->get_first_name() : '',
                            'required' => true
                        ] );
                        ?>
                    </div>
                </div>
                <div class="gh-form-column col-1-of-2">
                    <div class="gh-form-field">
                        <?php
                        echo html()->input( [
                            'type' => 'text',
                            'name' => 'last_name',
                            'id' => 'last_name',
                            'placeholder' => _e( 'Last Name' ),
                            'value' => $contact->get_last_name() ? $contact->get_last_name() : '',
                            'required' => true
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-1">
                    <div class="gh-form-field">

                        <?php
                        echo html()->input( [
                            'type' => 'email',
                            'name' => 'email',
                            'id' => 'email',
                            'placeholder' => _e( 'Email' ),
                            'value' => $contact->get_email() ? $contact->get_email() : '',
                            'required' => true
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-1">
                    <div class="gh-form-field">
                        <?php
                        echo html()->input( [
                            'type' => 'tel',
                            'name' => 'phone',
                            'id' => 'phone',
                            'placeholder' => _e( 'Phone' ),
                            'value' => $contact->get_phone_number() ? $contact->get_phone_number() : '',
                            'required' => true
                        ] );
                        ?>
                    </div>
                </div>
            </div>
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-1">
                    <div class="gh-form-field">

                        <?php
                        $book_text = apply_filters( 'groundhogg/calendar/shortcode/confirm_text', __( 'Book Appointment', 'groundhogg' ) );

                        echo html()->input( [
                            'type' => 'submit',
                            'name' => 'book_appointment',
                            'id' => 'book_appointment',
                            'value' => $book_text

                        ] ); ?>

                        <!--                        --><?php //$book_text = apply_filters( 'groundhogg/calendar/shortcode/confirm_text', __( 'Book Appointment', 'groundhogg' ) );
                        ?>
                        <!--                        <input type="submit" name="book_appointment" id="book_appointment" value="-->
                        <?php //esc_attr_e( $book_text );
                        ?><!--"/>-->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }


    /**
     * Adjust shades of colour for front end calendar
     *
     * @param $hex
     * @param $steps
     * @return string
     */
    private function adjust_brightness( $hex, $steps )
    {

        // Steps should be between -255 and 255. Negative = darker, positive = lighter
        $steps = max( -255, min( 255, $steps ) );

        // Normalize into a six character long hex string
        $hex = str_replace( '#', '', $hex );
        if ( strlen( $hex ) == 3 ) {
            $hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
        }

        // Split into three parts: R, G and B
        $color_parts = str_split( $hex, 2 );
        $return = '#';

        foreach ( $color_parts as $color ) {
            $color = hexdec( $color ); // Convert to decimal
            $color = max( 0, min( 255, $color + $steps ) ); // Adjust color
            $return .= str_pad( dechex( $color ), 2, '0', STR_PAD_LEFT ); // Make two char hex code
        }

        return $return;
    }

}

