<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use GroundhoggBookingCalendar\Classes\Email_Reminder;
use function Groundhogg\enqueue_groundhogg_modal;
use function Groundhogg\get_array_var;
use function Groundhogg\get_request_var;
use function Groundhogg\groundhogg_url;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;
use function GroundhoggBookingCalendar\days_of_week;

/**
 * @var $calendar Calendar;
 */

$calendar_id = absint( get_request_var( 'calendar' ) );
$calendar    = new Calendar( $calendar_id );

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

$reminders = $calendar->get_meta( 'email_reminders' ); // TODO get the reminders

function reminder_name( $att = '' ) {
	return sprintf( 'email_reminders[%s][]', $att );
}

if ( empty( $reminders ) ) {
	$reminders = [ '' ];
}

foreach ( $reminders as $reminder ):

	$rows[] = [

		html()->wrap( [
			html()->number( [
				'class' => 'input',
				'name'  => reminder_name( 'number' ),
				'value' => get_array_var( $reminder, 'number' ),
			] ),
			// month|day|year
			html()->dropdown( [
				'name'        => reminder_name( 'period' ),
				'selected'    => get_array_var( $reminder, 'period' ),
				'option_none' => false,
				'options'     => [
					'minutes' => __( 'Minute(s)' ),
					'hours'   => __( 'Hour(s)' ),
					'days'    => __( 'Day(s)' ),
					'weeks'   => __( 'Week(s)' ),
					'months'  => __( 'Month(s)' ),
				],
			] ),
			html()->dropdown( [
				'name'        => reminder_name( 'when' ),
				'options'     => [
					'before' => __( 'Before', 'groundhogg-calendar' ),
					'after'  => __( 'After', 'groundhogg-calendar' )
				],
				'option_none' => false,
				'selected'    => get_array_var( $reminder, 'when' ),
			] ),
			"&nbsp;",
			__( 'Appointment' ),
		], 'div', [] ),
		html()->wrap( [
			html()->wrap(
				html()->dropdown_emails( [
					'name'     => reminder_name( 'email_id' ),
					'id'       => '',
					'selected' => absint( get_array_var( $reminder, 'email_id' ) ),
				] ), 'div', [
					'style' => [ 'max-width' => '300px', 'display' => 'inline-block', 'margin-right' => '5px' ]
				]
			),
			html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-email' ], __( 'Edit Email' ) ),
		], 'div' ),

		html()->wrap( [
			html()->wrap( html()->e( 'a', [ 'href'  => '#trash',
			                                'class' => 'delete trash trash-rule'
			], '<span class="dashicons dashicons-trash"></span>' ), 'span', [ 'class' => 'delete' ] ),
			' | ',
			html()->e( 'a', [ 'href'  => '#add',
			                  'class' => 'add add-rule'
			], '<span class="dashicons dashicons-plus"></span>' ),
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

	html()->th( __( 'Appointment Scheduled', 'groundhogg-calendar' ) );
	html()->td( [
		html()->dropdown_emails( [
			'name'     => Email_Reminder::SCHEDULED,
			'id'       => Email_Reminder::SCHEDULED,
			'selected' => $calendar->get_email_notification( Email_Reminder::SCHEDULED ),
		] ),
		'&nbsp;',
		html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-email' ], __( 'Edit Email' ) ),
		html()->description( __( 'Email that is sent when an appointment is booked.', 'groundhogg-calendar' ) ),
	] );
	html()->end_row();

	html()->start_row();
	html()->th( __( 'Appointment Rescheduled', 'groundhogg-calendar' ) );
	html()->td( [
		html()->dropdown_emails( [
			'name'     => Email_Reminder::RESCHEDULED,
			'id'       => Email_Reminder::RESCHEDULED,
			'selected' => $calendar->get_email_notification( Email_Reminder::RESCHEDULED ),
		] ),
		'&nbsp;',
		html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-email' ], __( 'Edit Email' ) ),
		html()->description( __( 'Email that is sent when an appointment is rescheduled.', 'groundhogg-calendar' ) ),
	] );
	html()->end_row();

	html()->start_row();
	html()->th( __( 'Appointment Cancelled', 'groundhogg-calendar' ) );
	html()->td( [
		html()->dropdown_emails( [
			'name'     => Email_Reminder::CANCELLED,
			'id'       => Email_Reminder::CANCELLED,
			'selected' => $calendar->get_email_notification( Email_Reminder::CANCELLED ),
		] ),
		'&nbsp;',
		html()->e( 'a', [ 'href' => '#', 'class' => 'button edit-email' ], __( 'Edit Email' ) ),
		html()->description( __( 'Email that is sent when an appointment is cancelled.', 'groundhogg-calendar' ) ),
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