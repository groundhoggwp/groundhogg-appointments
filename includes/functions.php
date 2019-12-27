<?php

namespace GroundhoggBookingCalendar;


use Groundhogg\Email;
use Groundhogg\Event;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use GroundhoggSMS\Classes\SMS;
use mysql_xdevapi\Exception;
use function Groundhogg\admin_page_url;
use function Groundhogg\get_array_var;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Reminder;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_form_list;
use function Groundhogg\get_request_var;
use function Groundhogg\groundhogg_url;

function convert_to_client_timezone( $time, $timezone = '' )
{
    if ( !$timezone ) {
        return $time;
    }

    if ( current_user_can( 'edit_calendar' ) ) {
        $local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_local_time( $time );
        return $local_time;
    }

    try {
        $local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_foreign_time( $time, $timezone );
    } catch ( \Exception $e ) {
        // Use site time anyway.
        $local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_local_time( $time );
    }

    return $local_time;
}

/**
 * @param $value mixed
 * @param $a mixed Lower
 * @param $b mixed Higher
 * @return bool
 */
function in_between( $value, $a, $b )
{
    return ( $value > $a && $value < $b );
}

/**
 * @param $value mixed
 * @param $a mixed Lower
 * @param $b mixed Higher
 * @return bool
 */
function in_between_inclusive( $value, $a, $b )
{
    return ( $value >= $a && $value <= $b );
}

/**
 * Get the days of the week as an array, or if you pass a day get the display name of that day
 *
 * @param string $day
 * @return array|mixed
 */
function days_of_week( $day = '' )
{
    $days = [
        'monday' => __( 'Monday' ),
        'tuesday' => __( 'Tuesday' ),
        'wednesday' => __( 'Wednesday' ),
        'thursday' => __( 'Thursday' ),
        'friday' => __( 'Friday' ),
        'saturday' => __( 'Saturday' ),
        'sunday' => __( 'Sunday' ),
    ];

    if ( empty( $day ) ) {
        return $days;
    }

    return get_array_var( $days, $day, 'monday' );
}

/**
 * Adjust shades of colour for front end calendar
 *
 * @param $hex
 * @param $steps
 * @return string
 */
function adjust_brightness( $hex, $steps )
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

/**
 * Setup the step for an event as the Reminder notification type
 *
 * @param $event Event
 */
function setup_reminder_notification_object( $event )
{
    if ( $event->get_event_type() === Reminder::NOTIFICATION_TYPE ) {

        // Step ID will be the ID of the email
        // Funnel ID will be the ID of the appointment
        $event->step = new Reminder( $event->get_funnel_id(), $event->get_step_id() );
    }
}

add_action( 'groundhogg/event/post_setup', __NAMESPACE__ . '\setup_reminder_notification_object' );

/**
 * Schedule a 1 off reminder notification
 *
 * @param $email_id int the ID of the email to send
 * @param $appointment_id int|string the ID of the appointment being referenced
 * @param int $time time time to send at, defaults to time()
 *
 * @return bool whether the scheduling was successful.
 */
function send_reminder_notification( $email_id = 0, $appointment_id = 0, $time = 0 )
{
    $appointment = new Appointment( $appointment_id );
    $email = new Email( $email_id );

    if ( !$appointment->exists() || !$email->exists() ) {
        return false;
    }

    if ( !$time ) {
        $time = time();
    }

    $event = new Event( [
        'time' => $time,
        'funnel_id' => $appointment_id,
        'step_id' => $email->get_id(),
        'contact_id' => $appointment->get_contact_id(),
        'event_type' => Reminder::NOTIFICATION_TYPE,
        'status' => 'waiting',
    ] );

    if ( !$event->exists() ) {
        return false;
    }

    do_action( 'groundhogg/calendar/reminder_scheduled', $event );
    return true;

}


/**
 * Setup the step for an event as the Reminder notification type
 *
 * @param $event Event
 */
function setup_reminder_notification_object_sms( $event )
{
    if ( $event->get_event_type() === SMS_Reminder::NOTIFICATION_TYPE ) {

        // Step ID will be the ID of the email
        // Funnel ID will be the ID of the appointment
        $event->step = new SMS_Reminder( $event->get_funnel_id(), $event->get_step_id() );
    }
}

add_action( 'groundhogg/event/post_setup', __NAMESPACE__ . '\setup_reminder_notification_object_sms' );

/**
 * Schedule a 1 off reminder notification
 *
 * @param $sms_id int the ID of the email to send
 * @param $appointment_id int|string the ID of the appointment being referenced
 * @param int $time time time to send at, defaults to time()
 *
 * @return bool whether the scheduling was successful.
 */
function send_sms_reminder_notification( $sms_id = 0, $appointment_id = 0, $time = 0 )
{
    $appointment = new Appointment( absint( $appointment_id ) );
    $sms = new SMS( absint( $sms_id ) );

    if ( !$appointment->exists() || !$sms->exists() ) {
        return false;
    }

    if ( !$time ) {
        $time = time();
    }

    $event = new Event( [
        'time' => $time,
        'funnel_id' => $appointment_id,
        'step_id' => $sms->get_id(),
        'contact_id' => $appointment->get_contact_id(),
        'event_type' => SMS_Reminder::NOTIFICATION_TYPE,
        'status' => 'waiting',
    ] );

    if ( !$event->exists() ) {
        return false;
    }

    return true;

}


function is_sms_plugin_active()
{
    return \Groundhogg\is_sms_plugin_active();
}


function add_booking_appointment()
{
?>
<table  class="form-table">
    <tr>
        <th><?php _ex( 'Book Appointment', 'contact_record', 'groundhogg-calendar' ); ?></th>
        <td>
            <div style="max-width: 400px;">
                <?php
                $calendars = get_calendar_list();
                echo Plugin::$instance->utils->html->select2( [
                    'name' => 'appointment_booking_from_contact',
                    'id' => 'appointment_booking_from_contact',
                    'class' => 'appointment_booking_from_contact gh-select2',
                    'data' => $calendars,
                    'multiple' => false,
                    'placeholder' => __( 'Please select a calendar', 'groundhogg-calendar' ),
                ] );
                ?>
                <div class="row-actions">
                    <button type="submit" name="appointment_book" value="appointment_book"
                            class="button"><?php _e( 'Book Appointment', 'groundhogg-calendar' ); ?></button>
                </div>
            </div>
        </td>
    </tr>
</table>
<?php
}

add_action( 'groundhogg/admin/contact/record/tab/actions' , __NAMESPACE__ . '\add_booking_appointment' , 12);




function get_calendar_list()
{

    $calendars = Plugin::$instance->dbs->get_db( 'calendars' )->query();

    $calendar_list = array();
    $default = 0;
    foreach ( $calendars as $calendar ) {
        if ( !$default ) {
            $default = $calendar->ID;
        }
        $calendar_list[ $calendar->ID ] = $calendar->name;
    }

    return $calendar_list;
}

/**
 * Action for adding an appointment via the contact screen
 *
 * @param $contact_id
 * @param $contact
 */
function display_calendar_contact($contact_id , $contact )
{
    if ( get_request_var( 'appointment_book' ) ) {
        wp_safe_redirect( admin_page_url('gh_calendar' ,  [
            'action' => 'edit',
            'contact' => $contact_id,
            'calendar' => absint( get_request_var( 'appointment_booking_from_contact' ) ),
        ]));
    }
}

add_action('groundhogg/admin/contact/save' , __NAMESPACE__. '\display_calendar_contact', 10 , 2 );


/**
 * Convert a duration to human readable format.
 *
 * @since 5.1.0
 *
 * @param string $duration Duration will be in string format (HH:ii:ss) OR (ii:ss),
 *                         with a possible prepended negative sign (-).
 * @return string|false A human readable duration string, false on failure.
 */
function better_human_readable_duration( $duration = '' ) {
    if ( ( empty( $duration ) || ! is_string( $duration ) ) ) {
        return false;
    }

    $duration = trim( $duration );

    // Remove prepended negative sign.
    if ( '-' === substr( $duration, 0, 1 ) ) {
        $duration = substr( $duration, 1 );
    }

    // Extract duration parts.
    $duration_parts = array_reverse( explode( ':', $duration ) );
    $duration_count = count( $duration_parts );

    $hour   = null;
    $minute = null;
    $second = null;

    if ( 3 === $duration_count ) {
        // Validate HH:ii:ss duration format.
        if ( ! ( (bool) preg_match( '/^([0-9]+):([0-5]?[0-9]):([0-5]?[0-9])$/', $duration ) ) ) {
            return false;
        }
        // Three parts: hours, minutes & seconds.
        list( $second, $minute, $hour ) = $duration_parts;
    } elseif ( 2 === $duration_count ) {
        // Validate ii:ss duration format.
        if ( ! ( (bool) preg_match( '/^([0-5]?[0-9]):([0-5]?[0-9])$/', $duration ) ) ) {
            return false;
        }
        // Two parts: minutes & seconds.
        list( $second, $minute ) = $duration_parts;
    } else {
        return false;
    }

    $human_readable_duration = array();

    // Add the hour part to the string.
    if ( is_numeric( $hour ) && $hour > 0 ) {
        /* translators: Time duration in hour or hours. */
        $human_readable_duration[] = sprintf( _n( '%s hour', '%s hours', $hour ), (int) $hour );
    }

    // Add the minute part to the string.
    if ( is_numeric( $minute ) && $minute > 0  ) {
        /* translators: Time duration in minute or minutes. */
        $human_readable_duration[] = sprintf( _n( '%s minute', '%s minutes', $minute ), (int) $minute );
    }

    // Add the second part to the string.
    if ( is_numeric( $second ) && $second > 0  ) {
        /* translators: Time duration in second or seconds. */
        $human_readable_duration[] = sprintf( _n( '%s second', '%s seconds', $second ), (int) $second );
    }

    return implode( ', ', $human_readable_duration );
}


/**
 * Wrapper function to get the date format.
 *
 * @return mixed|void
 */
function get_date_format()
{
    return get_option( 'date_format' );
}

/**
 * Wrapper function to get the time format.
 *
 * @return mixed|void
 */
function get_time_format(){
    return get_option( 'time_format' );
}

/**
 * Get the unix stamp to show in the given timezone
 *
 * @param $time
 * @param $time_zone
 *
 * @return int
 */
function get_in_time_zone( $time, $time_zone){
    try{
        return Plugin::$instance->utils->date_time->convert_to_foreign_time( $time, $time_zone );
    } catch (\Exception $exception){
        return $time;
    }
}

/**
 * Get the tz db name
 *
 * @return string
 */
function get_tz_db_name()
{
    $offset = Plugin::$instance->utils->date_time->get_wp_offset( true );

	$tz = timezone_name_from_abbr('', $offset, 1);
    // Workaround for bug #44780
	if($tz === false) $tz = timezone_name_from_abbr('', $offset, 0);

	return $tz;
}