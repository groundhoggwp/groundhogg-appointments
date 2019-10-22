<?php
namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\get_array_var;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;
use function GroundhoggBookingCalendar\days_of_week;

$cols = [
    __( 'Day' ),
    __( 'Start' ),
    __( 'End' ),
    __( 'Actions' ),
];

/**
 * @var $calendar Calendar;
 */

$calendar_id = absint( get_request_var( 'calendar' ) );
$calendar = new Calendar( $calendar_id );

$rules = $calendar->get_meta( 'rules' ); // TODO Get rules somehow.

if ( ! $rules ){

    $rules = [];

    $times = [ 'start' => '09:00', 'end' => '17:00' ];

    $days = days_of_week();
    $days = array_keys( $days );

    foreach ( $days as $day ){
        $rules[] = array_merge( [ 'day' => $day ], $times );
    }
}

$rows = [];

function rule_name( $att='' ){
    return sprintf( 'rules[%s][]', $att );
}

foreach ( $rules as $rule ):

    $rows[] = [

        html()->dropdown( [
            'name' => rule_name(  'day' ),
            'options' => days_of_week(),
            'selected' => get_array_var( $rule, 'day' ),
        ] ),

        html()->input( [
            'type' => 'time',
            'name' => rule_name(  'start' ),
            'value' => get_array_var( $rule, 'start' ),
            'class' => 'input'
        ] ),
        html()->input( [
            'type' => 'time',
            'name' => rule_name(  'end' ),
            'value' => get_array_var( $rule, 'end' ),
            'class' => 'input'
        ] ),
        html()->wrap( [
            html()->wrap( html()->e( 'a', [ 'href' => '#trash', 'class' => 'delete trash trash-rule' ], '<span class="dashicons dashicons-trash"></span>' ), 'span', [ 'class' => 'delete' ] ),
            ' | ',
            html()->e( 'a', [ 'href' => '#add', 'class' => 'add add-rule' ], '<span class="dashicons dashicons-plus"></span>' ),
        ], 'span', [ 'class' => 'row-actions' ] )

    ];

endforeach;

?>
<form method="post">
    <?php wp_nonce_field(); ?>
    <h2><?php _e('Availability', 'groundhogg-calendar'); ?></h2>
    <style>select{ vertical-align: top !important;}</style>

    <?php
    html()->start_form_table();

    html()->start_row();

    html()->th( __( 'Min time before Booking ' ) );
    html()->td( [
        // number
        html()->number( [
            'name' => 'min_booking_period_count',
            'value' => $calendar->get_meta( 'min_booking_period_count' ),
            'class' => 'input'
        ] ),
        // month|day|year
        html()->dropdown( [
            'name' => 'min_booking_period_type',
            'selected' => $calendar->get_meta( 'min_booking_period_type' ),
            'options' => [
                'hours'     => __( 'Hours' ),
                'days'      => __( 'Days' ),
                'weeks'     => __( 'Weeks' ),
                'months'    => __( 'Months' ),
            ],
        ]),
        html()->description( __( 'The minimum amount of time from the current date and time someone can book an appointment.', 'groundhogg-calendar' ) ),
    ] );

    html()->end_row();


    html()->start_row();

    html()->th( __( 'Max. booking period' ) );
    html()->td( [
        // number
        html()->number( [
            'name' => 'max_booking_period_count',
            'value' => $calendar->get_meta( 'max_booking_period_count' ),
            'class' => 'input'
        ] ),
        // month|day|year
        html()->dropdown( [
            'name' => 'max_booking_period_type',
            'selected' => $calendar->get_meta( 'max_booking_period_type' ),
            'options' => [
                'days'      => __( 'Days' ),
                'weeks'     => __( 'Weeks' ),
                'months'    => __( 'Months' ),
            ],
        ]),
        html()->description( __( 'The maximum amount of time from the current day that someone can book an appointment.', 'groundhogg-calendar' ) ),
    ] );

    html()->end_row();

    html()->end_form_table();


    ?>

<?php

html()->list_table( [ 'style' => [ 'max-width' => '700px' ] ], $cols, $rows );

?>
<script>
    (function ($) {
        $(function () {
            $( document ).on( 'click', '.trash-rule', function (e) {
                e.preventDefault();
                $(e.target).closest( 'tr' ).remove();
            } );

            $( document ).on( 'click', '.add-rule', function (e) {
                e.preventDefault();
                var $row =  $(e.target).closest( 'tr' );
                $row.clone().insertAfter( $row );
            } );
        })
    })(jQuery)
</script>
<?php


submit_button( __( 'Update Availabiliy', 'groundhogg-calendar' ) );
?>
</form>
