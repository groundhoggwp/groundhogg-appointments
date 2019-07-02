<?php

namespace GroundhoggBookingCalendar;

use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;

$calendar_id = get_query_var( 'calendar_id' );

$calendar = new Calendar( $calendar_id );

if ( !$calendar->exists() ) {
    wp_die( 'Calendar does not exist.' );
}

/**
 * The default form to show if no form is provided.
 */
function default_form()
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
                    'class' => 'gh-input',
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
                    'class' => 'gh-input',
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
                    'class' => 'gh-input',
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
                    'class' => 'gh-input',
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
            </div>
        </div>
    </div>
</div>
<?php
}

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_scripts()
{
    global $calendar_id, $calendar;

    wp_enqueue_script( 'groundhogg-appointments-frontend' );

    wp_localize_script( 'groundhogg-appointments-frontend', 'BookingCalendar', [
        'calendar_id'    => $calendar_id,
        'start_of_week'  => get_option( 'start_of_week' ),
        'max_date'       => $calendar->get_max_booking_period( true ),
        'disabled_days'  => $calendar->get_dates_no_slots(),
        'day_names'      => [ "SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT" ],
        'ajaxurl'        => admin_url( 'admin-ajax.php' ),
        'invalidDateMsg' => __('Please select a valid time slot.', 'groundhogg')
    ] );

    do_action( 'enqueue_groundhogg_calendar_scripts' );
}

add_action( 'wp_enqueue_scripts', '\GroundhoggBookingCalendar\enqueue_calendar_scripts' );

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_styles()
{
    wp_enqueue_style( 'jquery-ui-datepicker' );
    wp_enqueue_style( 'groundhogg-calendar-frontend' );
    wp_enqueue_style( 'groundhogg-form' );
    wp_enqueue_style( 'groundhogg-loader' );

    do_action( 'enqueue_groundhogg_calendar_styles' );
}

add_action( 'wp_enqueue_scripts', '\GroundhoggBookingCalendar\enqueue_calendar_styles' );


?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <base target="_parent">
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <title><?php echo $calendar->get_name(); ?></title>
    <?php wp_head(); ?>
    <script>
        (function ($) {

            var CachedEvent  = {};

            window.addEventListener( 'message', function (event) {
                if ( typeof event.data.action !== "undefined" && event.data.action === "getFrameSize") {
                    CachedEvent = event;
                    postSizing()
                }
            });

            function postSizing() {
                var body = document.body, html = document.documentElement;
                var height = Math.max(body.scrollHeight, body.offsetHeight,
                    html.clientHeight, html.scrollHeight, html.offsetHeight);
                var width = '100%';
                CachedEvent.source.postMessage({ height: height, width: width, id: CachedEvent.data.id }, "*");
            }

            $(document).on( 'click', function () {
                postSizing();
            } );
        })( jQuery );

    </script>
</head>
<body class="groundhogg-calendar-body">
<div id="main" style="padding: 20px">
    <?php

    $title = $calendar->get_meta( 'slot_title', true );

    if ( $title === null ) {
        $title = __( 'Time Slot', 'groundhogg' );
    }

    ?>
    <div class="loader-overlay hidden"></div>
    <div class="loader-wrap hidden">
        <p><span class="gh-loader"></span></p>
    </div>
    <div class="calendar-form-wrapper">
        <form class="gh-calendar-form" method="post" action="">
            <div class="gh-form">
                <div class="gh-form-row clearfix">
                    <div class="booking-calendar-column gh-form-column col-1-of-1">
                        <div class="groundhogg-calendar">
                            <div id="booking-calendar" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="gh-form-column col-1-of-3 hidden">
                        <div id="time-slots" class="select-time hidden">
                            <p class="time-slot-select-text"><b><?php _e( $title, 'groundhogg' ); ?></b></p>

                            <hr class="time-slot-divider"/>
                            <div id="time-slots-inner"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="appointment-errors" class="gh-message-wrapper gh-form-errors-wrapper hidden"></div>
        </form>
        <div id="details-form" class="gh-form-wrapper hidden">
            <hr>
            <?php
            $override_form = absint( $calendar->get_meta( 'override_form_id' ) );
            if ( $override_form ) {
                echo do_shortcode( sprintf( '[gh_form id="%d" class="details-form"]', $override_form ) );
            } else {
                default_form();
            }
            ?>
        </div>
    </div>
</div>
<?php
wp_footer();
?>
<div class="clear"></div>
</body>
</html>
