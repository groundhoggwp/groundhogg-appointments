<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\enqueue_groundhogg_modal;
use function Groundhogg\get_array_var;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;

/**
 * @var $calendar Calendar;
 */

$calendar_id = absint( get_request_var( 'calendar' ) );
$calendar = new Calendar( $calendar_id );

$sms = $calendar->get_meta( 'sms' );

if ( !$sms ) {
    $sms = []; // TODO default sms!
}


wp_enqueue_script( 'groundhogg-sms-reminders' );
wp_localize_script( 'groundhogg-sms-reminders', 'CalendarReminders', [
    'edit_sms_path' => add_query_arg( [ 'page' => 'gh_sms', 'action' => 'edit' ], admin_url( 'admin.php' ) )
] );


$cols = [
    __( 'Time period' ),
    __( 'SMS' ),
    __( 'Actions' ),
];

$rows = [];

$reminders = $calendar->get_meta( 'sms_reminders' ); // TODO get the reminders

function reminder_name( $att = '' )
{
    return sprintf( 'sms_reminders[%s][]', $att );
}

if ( empty( $reminders ) ) {
    $reminders = [ '' ];
}

foreach ( $reminders as $reminder ):

    $rows[] = [

        html()->wrap( [
            html()->number( [
                'class' => 'input',
                'name' => reminder_name( 'number' ),
                'value' => get_array_var( $reminder, 'number' ),
            ] ),

            // month|day|year
            html()->dropdown( [
                'name' => reminder_name( 'period' ),
                'selected' => get_array_var( $reminder, 'period' ),
                'option_none' => false,
                'options' => [
                    'minutes' => __( 'Minute(s)' ),
                    'hours' => __( 'Hour(s)' ),
                    'days' => __( 'Day(s)' ),
                    'weeks' => __( 'Week(s)' ),
                    'months' => __( 'Month(s)' ),
                ],
            ] ),
            html()->dropdown( [
                'name' => reminder_name( 'when' ),
                'options' => [
                    'before' => __( 'Before', 'groundhogg-calendar' ),
                    'after' => __( 'After', 'groundhogg-calendar' )
                ],
                'option_none' => false,
                'selected' => get_array_var( $reminder, 'when' ),
            ] ),
            "&nbsp;",
            __( 'Appointment' ),
        ], 'div', [] ),
        html()->wrap( [
            html()->wrap(
                html()->dropdown_sms( [
                    'name' => reminder_name( 'sms_id' ),
                    'id' => '',
                    'selected' => absint( get_array_var( $reminder, 'sms_id' ) ),
                ] ), 'div', [ 'style' => [ 'max-width' => '300px', 'display' => 'inline-block', 'margin-right' => '5px' ]
                ]
            ),
            html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-sms' ], __( 'Edit SMS' ) ),
        ], 'div' ),

        html()->wrap( [
            html()->wrap( html()->e( 'a', [ 'href' => '#trash', 'class' => 'delete trash trash-rule' ], '<span class="dashicons dashicons-trash"></span>' ), 'span', [ 'class' => 'delete' ] ),
            ' | ',
            html()->e( 'a', [ 'href' => '#add', 'class' => 'add add-rule' ], '<span class="dashicons dashicons-plus"></span>' ),
        ], 'span', [ 'class' => 'row-actions' ] )

    ];

endforeach;

enqueue_groundhogg_modal();

?>
    <form name="" id="" method="post" action="">
        <?php
        wp_nonce_field();


        html()->start_form_table( [
            'title' => __( 'Notifications', 'groundhogg-calendar' )
        ] );


        html()->start_row();

        html()->th( __( 'SMS Notification', 'groundhogg-calendar' ) );
        html()->td( [
            html()->checkbox( [
                'name' => 'sms_notification',
                'id' => 'sms_notification',
                'checked' => $calendar->get_meta('sms_notification') ? $calendar->get_meta('sms_notification') : 0
            ] ),
            html()->description( __( 'Enable SMS reminders for appointments.', 'groundhogg-calendar' ) ),
        ] );

        html()->end_row();

        html()->start_row();

        html()->th( __( 'Appointment Booked', 'groundhogg-calendar' ) );
        html()->td( [
            html()->dropdown_sms( [
                'name' => 'appointment_booked',
                'id' => 'appointment_booked',
                'selected' => get_array_var(  $sms , 'appointment_booked' ) ,
            ] ),
            '&nbsp;',
            html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-sms' ], __( 'Edit SMS' ) ),
            html()->description( __( 'SMS that is sent when an appointment is booked.', 'groundhogg-calendar' ) ),
        ] );

        html()->end_row();

        html()->start_row();

        html()->th( __( 'Appointment Approved', 'groundhogg-calendar' ) );
        html()->td( [
            html()->dropdown_sms( [
                'name' => 'appointment_approved',
                'id' => 'appointment_approved',
                'selected' =>  get_array_var( $sms, 'appointment_approved' ) ,
            ] ),
            '&nbsp;',
            html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-sms' ], __( 'Edit SMS' ) ),
            html()->description( __( 'SMS that is sent when an appointment is approved.', 'groundhogg-calendar' ) ),
        ] );

        html()->end_row();
        html()->start_row();

        html()->th( __( 'Appointment Rescheduled', 'groundhogg-calendar' ) );
        html()->td( [
            html()->dropdown_sms( [
                'name' => 'appointment_rescheduled',
                'id' => 'appointment_rescheduled',
                'selected' => get_array_var( $sms, 'appointment_rescheduled' ),
            ] ),
            '&nbsp;',
            html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-sms' ], __( 'Edit SMS' ) ),
            html()->description( __( 'SMS that is sent when an appointment is rescheduled.', 'groundhogg-calendar' ) ),
        ] );

        html()->end_row();

        html()->start_row();

        html()->th( __( 'Appointment Cancelled', 'groundhogg-calendar' ) );
        html()->td( [
            html()->dropdown_sms( [
                'name' => 'appointment_cancelled',
                'id' => 'appointment_cancelled',
                'selected' => get_array_var( $sms , 'appointment_cancelled' ),
            ] ),
            '&nbsp;',
            html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-sms' ], __( 'Edit SMS' ) ),
            html()->description( __( 'SMS that is sent when an appointment is Cancelled.', 'groundhogg-calendar' ) ),
        ] );

        html()->end_row();

        html()->end_form_table();

        html()->start_form_table( [
            'title' => __( 'Reminders', 'groundhogg-calendar' )
        ] );

        html()->start_form_table();
        html()->end_form_table();

        ?>
        <style>.reminders tr th:last-child {
                width: 10%;
            }

            .wp-admin select {
                vertical-align: bottom;
            }</style>
        <?php

        html()->list_table( [ 'style' => [ 'max-width' => '1000px' ], 'class' => 'reminders ' ], $cols, $rows );

        submit_button();
        ?>
    </form>
<?php