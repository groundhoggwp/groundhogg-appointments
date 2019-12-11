<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Contact;
use function Groundhogg\get_current_contact;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;

$calendar_id = get_query_var('calendar_id');

$calendar = new Calendar($calendar_id);

if (!$calendar->exists()) {
    wp_die('Calendar does not exist.');
}

/**
 * The default form to show if no form is provided.
 */
function default_form()
{

$contact = get_current_contact();

?>
<div class="gh-form-wrapper">
    <form class="gh-form details-form" method="post" target="_parent">
        <div class="gh-form">
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-2">
                    <div class="gh-form-field">
                        <label class="gh-input-label">
                        <?php

                        _e( 'First *', 'groundhogg-calendar' );

                        echo html()->input([
                            'type' => 'text',
                            'name' => 'first_name',
                            'id' => 'first_name',
                            'class' => 'gh-input',
                            'placeholder' => __('John'),
                            'required' => true,
                            'value' => $contact ? $contact->get_first_name() : '',
                        ]);
                        ?>
                        </label>
                    </div>
                </div>
                <div class="gh-form-column col-1-of-2">
                    <div class="gh-form-field">
                        <label class="gh-input-label">
                        <?php

                        _e( 'Last *', 'groundhogg-calendar' );

                        echo html()->input([
                            'type' => 'text',
                            'name' => 'last_name',
                            'id' => 'last_name',
                            'class' => 'gh-input',
                            'placeholder' => __('Doe'),
                            'required' => true,
                            'value' => $contact ? $contact->get_last_name() : '',
                        ]);
                        ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-1">
                    <div class="gh-form-field">
                        <label class="gh-input-label">
                        <?php

                        _e( 'Email *', 'groundhogg-calendar' );

                        echo html()->input([
                            'type' => 'email',
                            'name' => 'email',
                            'id' => 'email',
                            'class' => 'gh-input',
                            'placeholder' => __('name@example.com'),
                            'required' => true,
                            'value' => $contact ? $contact->get_email() : '',
                        ]);
                        ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-1">
                    <div class="gh-form-field">
                        <label class="gh-input-label">
                        <?php
                        
                        _e( 'Phone *', 'groundhogg-calendar' );
                        
                        echo html()->input([
                            'type' => 'tel',
                            'name' => 'phone',
                            'id' => 'phone',
                            'class' => 'gh-input',
                            'placeholder' => __('+1 (555) 555-5555', 'groundhogg-calendar' ),
                            'required' => true,
                            'value' => $contact ? $contact->get_phone_number() : '',
                        ]);
                        ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="gh-form-row clearfix">
                <div class="gh-form-column col-1-of-1">
                    <div class="gh-form-field">
                        <?php
                        $book_text = apply_filters('groundhogg/calendar/shortcode/confirm_text', __('Book Appointment', 'groundhogg-calendar'));
                        echo html()->input([
                            'type' => 'submit',
                            'name' => 'book_appointment',
                            'id' => 'book_appointment',
                            'value' => $book_text
                        ]); ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php
}

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_scripts()
{
    global $calendar_id, $calendar;

    wp_enqueue_script('groundhogg-appointments-frontend');

    wp_localize_script('groundhogg-appointments-frontend', 'BookingCalendar', [
        'calendar_id' => $calendar_id,
        'start_of_week' => get_option('start_of_week'),
        'min_date' => $calendar->get_min_booking_period(true),
        'max_date' => $calendar->get_max_booking_period(true),
        'disabled_days' => $calendar->get_dates_no_slots(),
        'day_names' => ["SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"],
        'ajaxurl' => admin_url('admin-ajax.php'),
        'invalidDateMsg' => __('Please select a valid time slot.', 'groundhogg-calendar')
    ]);

    do_action('enqueue_groundhogg_calendar_scripts');
}

add_action('wp_enqueue_scripts', '\GroundhoggBookingCalendar\enqueue_calendar_scripts');

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_styles()
{
    wp_enqueue_style('jquery-ui-datepicker');
    wp_enqueue_style('groundhogg-calendar-frontend');
    wp_enqueue_style('groundhogg-form');
    wp_enqueue_style('groundhogg-loader');

    do_action('enqueue_groundhogg_calendar_styles');
}

add_action('wp_enqueue_scripts', '\GroundhoggBookingCalendar\enqueue_calendar_styles');

status_header(200);
header('Content-Type: text/html; charset=utf-8');
nocache_headers();

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <base target="_parent">
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <title><?php echo $calendar->get_name(); ?></title>
    <?php wp_head(); ?>
</head>
<body class="groundhogg-calendar-body">
<div id="main" style="padding: 20px">
    <?php

    $title = $calendar->get_meta('slot_title', true);

    if ($title === null) {
        $title = __('Time Slot', 'groundhogg-calendar');
    }

    ?>
    <div class="loader-overlay" style="display: none"></div>
    <div class="loader-wrap" style="display: none">
        <p><span class="gh-loader"></span></p>
    </div>
    <div id="calendar-description">
        <h4><?php esc_html_e( get_bloginfo( 'name' ) ); ?></h4>
        <h1><?php esc_html_e( $calendar->get_name() ); ?></h1>
        <div class="details">
            <div class="details-length"><span class="clock-icon"></span> <?php esc_html_e( $calendar->get_appointment_length_formatted() ); ?></div>
        </div>
        <p><?php esc_html_e( $calendar->get_description() );?></p>
    </div>
    <div id="calendar-form-wrapper" class="calendar-form-wrapper">
        <form class="gh-calendar-form" method="post" action="">
            <div id="gh-booking-wrap">
                <div class="gh-form">
                    <div class="gh-form-row clearfix">
                        <div class="booking-calendar-column gh-form-column col-1-of-1">
                            <div class="groundhogg-calendar" style="padding: 10px;">
                                <div id="booking-calendar" style="width: 100%"></div>
                            </div>
                        </div>
                        <div class="gh-form-column col-1-of-3">
                            <div id="time-slots" class="select-time hidden">
                                <p class="time-slot-select-text"><b><?php _e($title, 'groundhogg-calendar'); ?></b></p>
                                <hr class="time-slot-divider"/>
                                <div id="time-slots-inner"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div id="details-form" class="gh-form-wrapper hidden">
            <?php
            // Rescheduling...
            if (get_request_var('reschedule')) {

                $appointment_id = absint(get_request_var('reschedule'));

                echo "<form class='gh-form details-form' method='post' target='_parent'> ";

                echo html()->input([
                    'type' => 'hidden',
                    'name' => 'appointment',
                    'value' => $appointment_id
                ]);

                echo html()->input([
                    'type' => 'hidden',
                    'name' => 'event',
                    'value' => 'reschedule'
                ]);

                echo html()->button([
                    'type' => 'submit',
                    'text' => __( 'Reschedule Appointment', 'groundhogg-calendar' ),
                    'name' => 'reschedule',
                    'id' => 'reschedule',
                    'class' => 'button',
                    'value' => 'reschedule',
                ]);

                echo '</form>';

            } else if ( $calendar->has_linked_form() ) {

                ?><h3><?php _e( 'Enter Details', 'groundhogg-calendar' ); ?></h3><?php

                echo do_shortcode(sprintf('[gh_form id="%d" class="details-form"]', $calendar->get_linked_form()));
            } else {

                ?><h3><?php _e( 'Enter Details', 'groundhogg-calendar' ); ?></h3><?php

                default_form();
            }
            ?>
        </div>
    </div>
</div>
<div class="clear"></div>
</body>
</html>