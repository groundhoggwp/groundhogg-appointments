<?php
namespace GroundhoggBookingCalendar;


use Groundhogg\Email;
use Groundhogg\Event;
use Groundhogg\Event_Process;
use Groundhogg\SMS;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use function Groundhogg\get_array_var;
use function Groundhogg\get_db;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Reminder;

function convert_to_client_timezone($time, $timezone='' ){
    if ( ! $timezone ){
        return $time;
    }

    if ( current_user_can( 'edit_calendar' ) ){
        $local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_local_time( $time );
        return $local_time;
    }

    try {
        $local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_foreign_time( $time, $timezone );
    } catch (\Exception $e ){
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
function in_between( $value, $a, $b ){
    return ( $value > $a && $value < $b );
}

/**
 * @param $value mixed
 * @param $a mixed Lower
 * @param $b mixed Higher
 * @return bool
 */
function in_between_inclusive( $value, $a, $b ){
    return ( $value >= $a && $value <= $b );
}

/**
 * Get the days of the week as an array, or if you pass a day get the display name of that day
 *
 * @param string $day
 * @return array|mixed
 */
function days_of_week( $day='' )
{
    $days = [
        'monday'    => __( 'Monday' ),
        'tuesday'   => __( 'Tuesday' ),
        'wednesday' => __( 'Wednesday' ),
        'thursday'  => __( 'Thursday' ),
        'friday'    => __( 'Friday' ),
        'saturday'  => __( 'Saturday' ),
        'sunday'    => __( 'Sunday' ),
    ];

    if ( empty( $day ) ){
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
    if ( $event->get_event_type() === Reminder::NOTIFICATION_TYPE ){

        // Step ID will be the ID of the email
        // Funnel ID will be the ID of the appointment
        $event->step = new Reminder( $event->get_funnel_id(), $event->get_step_id() );
    }
}

add_action( 'groundhogg/event/post_setup', __NAMESPACE__.'\setup_reminder_notification_object' );

/**
 * Schedule a 1 off reminder notification
 *
 * @param $email_id int the ID of the email to send
 * @param $appointment_id int|string the ID of the appointment being referenced
 * @param int $time time time to send at, defaults to time()
 *
 * @return bool whether the scheduling was successful.
 */
function send_reminder_notification( $email_id=0, $appointment_id=0, $time=0 )
{
    $appointment = new Appointment( $appointment_id );
    $email = new Email( $email_id );

    if ( ! $appointment->exists() || ! $email->exists() ){
        return false;
    }

    if ( ! $time ){
        $time = time();
    }

    $event = new Event([
        'time'          => $time,
        'funnel_id'     => $appointment_id,
        'step_id'       => $email->get_id(),
        'contact_id'    => $appointment->get_contact_id(),
        'event_type'    => Reminder::NOTIFICATION_TYPE,
        'status'        => 'waiting',
    ]);

    if ( ! $event->exists() ){
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
    if ( $event->get_event_type() === SMS_Reminder::NOTIFICATION_TYPE ){

        // Step ID will be the ID of the email
        // Funnel ID will be the ID of the appointment
        $event->step = new SMS_Reminder( $event->get_funnel_id(), $event->get_step_id() );
    }
}

add_action( 'groundhogg/event/post_setup', __NAMESPACE__.'\setup_reminder_notification_object_sms' );

/**
 * Schedule a 1 off reminder notification
 *
 * @param $sms_id int the ID of the email to send
 * @param $appointment_id int|string the ID of the appointment being referenced
 * @param int $time time time to send at, defaults to time()
 *
 * @return bool whether the scheduling was successful.
 */
function send_sms_reminder_notification( $sms_id=0, $appointment_id=0, $time=0 )
{
    $appointment = new Appointment( absint( $appointment_id ) );
    $sms = new SMS( absint(  $sms_id ) );

    if ( ! $appointment->exists() || ! $sms->exists() ){
        return false;
    }

    if ( ! $time ){
        $time = time();
    }

    $event = new Event([
        'time'          => $time,
        'funnel_id'     => $appointment_id,
        'step_id'       => $sms->get_id(),
        'contact_id'    => $appointment->get_contact_id(),
        'event_type'    => SMS_Reminder::NOTIFICATION_TYPE,
        'status'        => 'waiting',
    ]);

    if ( ! $event->exists() ){
        return false;
    }

    return true;

}