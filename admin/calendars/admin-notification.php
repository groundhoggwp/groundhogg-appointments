<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use Groundhogg\Plugin;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
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
$calendar    = new Calendar( $calendar_id );

?>
<form name="" id="" method="post" action="">
	<?php
	wp_nonce_field();


	echo html()->e( 'h3', [], __( 'Admin Notifications', 'groundhogg-calendar' ) );

	echo html()->description( __( 'Select when you want to receive admin notifications.', 'groundhogg-calendar' ) );

	html()->start_form_table( [] );

	html()->start_row();
	html()->th( __( 'Appointment Scheduled ', 'groundhogg-calendar' ) );
	html()->td( [
		html()->checkbox( [
			'name'    => 'scheduled_notification',
			'label'   => __( 'Enabled', 'groundhogg' ),
			'checked' => $calendar->is_admin_notification_enabled( Email_Reminder::SCHEDULED )

		] ),
		html()->description( __( 'Email that is sent when an appointment is booked.', 'groundhogg-calendar' ) ),
	] );

	html()->end_row();


	html()->start_row();
	html()->th( __( 'Appointment Rescheduled', 'groundhogg-calendar' ) );
	html()->td( [
		html()->checkbox( [
			'name'    => 'rescheduled_notification',
			'label'   => __( 'Enabled', 'groundhogg' ),
			'checked' => $calendar->is_admin_notification_enabled( Email_Reminder::RESCHEDULED )
		] ),
		html()->description( __( 'Get notified when an appointment is rescheduled.', 'groundhogg-calendar' ) ),
	] );

	html()->end_row();

	html()->start_row();
	html()->th( __( 'Appointment Cancelled ', 'groundhogg-calendar' ) );
	html()->td( [
		html()->checkbox( [
			'name'    => 'cancelled_notification',
			'label'   => __( 'Enabled', 'groundhogg' ),
			'checked' => $calendar->is_admin_notification_enabled( Email_Reminder::CANCELLED )
		] ),
		html()->description( __( 'Email that is sent when an appointment is cancelled.', 'groundhogg-calendar' ) ),
	] );

	html()->end_row();

	if ( is_sms_plugin_active() ) {
		html()->start_row();
		html()->th( __( 'Receive SMS Notifications', 'groundhogg-calendar' ) );
		html()->td( [
			html()->checkbox( [
				'name'    => 'admin_sms_notifications',
				'label'   => __( 'Enabled', 'groundhogg' ),
				'checked' => $calendar->is_admin_notification_enabled( 'sms' )
			] ),
			html()->description( __( 'Also receive SMS notifications in addition to email notifications.', 'groundhogg-calendar' ) ),
		] );

		html()->end_row();
	}
	html()->end_form_table();

	echo html()->e( 'h3', [], __( 'Notification', 'groundhogg-calendar' ) );

	echo html()->description( __( 'These notifications will be sent to the calendar owner.', 'groundhogg-calendar' ) );

	html()->start_form_table();
	html()->start_row();
	html()->th( __( 'Email recipients', 'groundhogg-calendar' ) );
	html()->td( [
		html()->input( [
			'name'  => 'admin_notification_email_recipients',
			'value' => $calendar->get_meta( 'admin_notification_email_recipients' ) ?: '{owner_email}',
		] ),
		html()->description( __( 'Use any email address or the {owner_email} replacement code. Separate multiple addresses with a <code>,</code>.', 'groundhogg-calendar' ) ),
	] );
	html()->end_row();
	html()->start_row();
	html()->th( __( 'SMS recipients', 'groundhogg-calendar' ) );
	html()->td( [
		html()->input( [
			'name'  => 'admin_notification_sms_recipients',
			'value' => $calendar->get_meta( 'admin_notification_sms_recipients' ) ?: '{owner_phone}',
		] ),
		html()->description( __( 'Use any phone number or the {owner_phone} replacement code. Separate multiple numbers with a <code>,</code>. Use <code>+</code> at the beginning of the number.', 'groundhogg-calendar' ) ),
	] );
	html()->end_row();
	html()->start_row();
	html()->th( __( 'Subject', 'groundhogg-calendar' ) );
	html()->td( [
		html()->input( [
			'name'  => 'subject',
			'value' => $calendar->get_meta( 'subject' ) ? $calendar->get_meta( 'subject' ) : "{full_name}",
		] ),
		html()->description( __( 'Subject of email the email notification. Will also include the appointment status.', 'groundhogg-calendar' ) ),
	] );
	html()->end_row();
	html()->start_row();
	html()->th( __( 'Content', 'groundhogg-calendar' ) );

	?>
	<td>
		<p>
			<?php Plugin::instance()->replacements->show_replacements_dropdown(); ?>
		</p>
		<?php
		echo html()->textarea( [
			'name'  => 'notification',
			'value' => $calendar->get_meta( 'notification' ) ? $calendar->get_meta( 'notification' ) : "Name: {full_name} \nEmail: {email} \n\nStart: {appointment_start_time_admin} \nEnd: {appointment_end_time_admin}",
			'style' => [ 'width' => '600px' ]

		] );
		echo html()->description( __( 'Any details you want to include about the appointment.', 'groundhogg-calendar' ) ); ?>
	</td><?php

	html()->end_row();

	html()->end_form_table();

	submit_button();

	?>
</form>