<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use Groundhogg\Plugin;
use function Groundhogg\enqueue_groundhogg_modal;
use function Groundhogg\get_array_var;
use function Groundhogg\get_request_var;
use function Groundhogg\groundhogg_url;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;
use function GroundhoggBookingCalendar\days_of_week;
use function GroundhoggBookingCalendar\is_sms_plugin_active;

/**
 * @var $calendar Calendar;
 */

$calendar_id = absint( get_request_var( 'calendar' ) );
$calendar = new Calendar( $calendar_id );

$emails = $calendar->get_meta( 'emails' );

if ( !$emails ) {
    $emails = []; // TODO default emails!
}

wp_enqueue_script( 'groundhogg-appointments-reminders' );
wp_localize_script( 'groundhogg-appointments-reminders', 'CalendarReminders', [
    'edit_email_path' => add_query_arg( [ 'page' => 'gh_emails', 'action' => 'edit' ], admin_url( 'admin.php' ) )
] );


$cols = [
    __( 'Time period' ),
    __( 'Email' ),
    __( 'Actions' ),
];

$rows = [];

enqueue_groundhogg_modal();

?>
<form name="" id="" method="post" action="">
    <?php
    wp_nonce_field();


    echo html()->e( 'h3', [], __( 'Notification Event', 'groundhogg-calendar' ));

    echo html()->description( __( 'Select when you want to receive admin notifications.', 'groundhogg-calendar' ) );

    html()->start_form_table( [] );

    html()->start_row();
    html()->th( __( 'Appointment Booked ', 'groundhogg-calendar' ) );
    html()->td( [
        html()->checkbox( [
            'name' => 'booked_admin',
            'label' => 'Enabled',
            'checked' => $calendar->get_meta( 'booked_admin' )

        ] ),
        html()->description( __( 'Email that is sent when an appointment is booked.', 'groundhogg-calendar' ) ),
    ] );

    html()->end_row();


    html()->start_row();
    html()->th( __( 'Appointment Reschedule ', 'groundhogg-calendar' ) );
    html()->td( [
        html()->checkbox( [
            'name' => 'reschedule_admin',
            'label' => 'Enabled',
            'checked' => $calendar->get_meta( 'reschedule_admin' )

        ] ),
        html()->description( __( 'Email that is sent when an appointment is reschedule.', 'groundhogg-calendar' ) ),
    ] );

    html()->end_row();


    html()->start_row();
    html()->th( __( 'Appointment Approved ', 'groundhogg-calendar' ) );
    html()->td( [
        html()->checkbox( [
            'name' => 'approved_admin',
            'label' => 'Enabled',
            'checked' => $calendar->get_meta( 'approved_admin' )

        ] ),
        html()->description( __( 'Email that is sent when an appointment is aproved.', 'groundhogg-calendar' ) ),
    ] );

    html()->end_row();


    html()->start_row();
    html()->th( __( 'Appointment Cancelled ', 'groundhogg-calendar' ) );
    html()->td( [
        html()->checkbox( [
            'name' => 'cancelled_admin',
            'label' => 'Enabled',
            'checked' => $calendar->get_meta( 'cancelled_admin' )

        ] ),
        html()->description( __( 'Email that is sent when an appointment is cancelled.', 'groundhogg-calendar' ) ),
    ] );

    html()->end_row();

    if ( is_sms_plugin_active() ) {


	    html()->start_row();
	    html()->th( __( 'SMS Notification ', 'groundhogg-calendar' ) );
	    html()->td( [
		    html()->checkbox( [
			    'name'    => 'sms_admin_notification',
			    'label'   => 'Enabled',
			    'checked' => $calendar->get_meta( 'sms_admin_notification' )

		    ] ),
		    html()->description( __( 'SMS notification send to Calendar owner along with email.', 'groundhogg-calendar' ) ),
	    ] );

	    html()->end_row();
    }
    html()->end_form_table();

    echo html()->e( 'h3', [], __( 'Notification Text', 'groundhogg-calendar' ) );

    echo html()->description( __( 'These notifications will be sent to the calendar owner.', 'groundhogg-calendar' ) );

    html()->start_form_table();

    html()->start_row();
    html()->th( __( 'Subject', 'groundhogg-calendar' ) );
    html()->td( [
        sprintf( "Appointment Status: %s", html()->input( [
            'name' => 'subject',
            'value' => $calendar->get_meta('subject') ? $calendar->get_meta('subject')  :  "{full_name}",
        ] ) ),
        html()->description( __( 'Subject of email', 'groundhogg-calendar' ) ),
    ] );

    wp_enqueue_script( 'groundhogg-admin-replacements' );

    html()->end_row();
    html()->start_row();
    html()->th( __( 'Notification', 'groundhogg-calendar' ) );
    html()->td( [
        html()->e('div', [ 'style' => [ 'margin-bottom' => '10px' ] ],
        html()->modal_link( array(
            'title' => __( 'Replacements', 'groundhogg' ),
            'text' => '<span style="vertical-align: middle" class="dashicons dashicons-admin-users"></span>&nbsp;' . _x( 'Insert Replacement', 'replacement', 'groundhogg' ),
            'footer_button_text' => __( 'Insert' ),
            'id' => 'replacements',
            'class' => 'button button-secondary no-padding replacements replacements-button',
            'source' => 'footer-replacement-codes',
            'height' => 900,
            'width' => 700,
        ) ) ),
        html()->textarea( [
            'name' => 'notification',
            'value' => $calendar->get_meta('notification') ? $calendar->get_meta('notification') :  "Name: {full_name} \nEmail: {email} \n\nStart: {appointment_start_time_admin} \nEnd: {appointment_end_time_admin}",
            'style' => [ 'width' => '600px']

        ] ),
        html()->description( __( 'Any details you want to include about the appointment.', 'groundhogg-calendar' ) ),
    ] );

    html()->end_row();

    html()->end_form_table();

    submit_button();

    ?>
</form>