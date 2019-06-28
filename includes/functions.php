<?php
namespace GroundhoggBookingCalendar;


use function Groundhogg\get_array_var;

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
