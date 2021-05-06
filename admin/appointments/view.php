<?php

namespace GroundhoggBookingCalendar\Admin\Appointments;

use GroundhoggBookingCalendar\Classes\Appointment;
use function Groundhogg\admin_page_url;
use function Groundhogg\dashicon;
use function Groundhogg\dashicon_e;
use function Groundhogg\html;
use function Groundhogg\utils;
use function GroundhoggBookingCalendar\get_date_format;
use function GroundhoggBookingCalendar\get_time_format;

/**
 * @var $appointment Appointment
 */

$contact        = $appointment->get_contact();
$calendar_owner = get_userdata( $appointment->get_calendar()->get_user_id() );

?>
<div class="appointment">
	<h2 class="appointment-title"><?php printf( __( '<b>%s</b> with %s', 'groundhogg-calendar' ),
			$contact->get_full_name(),
			$calendar_owner->ID === get_current_user_id() ? __( 'you' ) : sprintf( '%s %s', $calendar_owner->first_name, $calendar_owner->last_name ) ) ?></h2>
	<p class="calendar-identity"><?php printf( __( 'Calendar: <b>%s</b>', 'groundhogg-calendar' ), html()->e( 'a', [
			'href' => admin_page_url( 'gh_calendar', [
				'action'   => 'edit',
				'calendar' => $appointment->get_calendar_id()
			] )
		], $appointment->get_calendar()->get_name() ) ); ?></p>
	<div class="appointment-details">
		<p><?php dashicon_e( 'admin-users' ); ?> <b><?php _e( 'Contact' ) ?></b></p>
		<p><?php echo html()->e( 'a', [
				'href' => admin_page_url( 'gh_contacts', [
					'action'  => 'edit',
					'contact' => $appointment->get_contact_id()
				] )
			], sprintf( '%s (%s)', $appointment->get_contact()->get_full_name(), $appointment->get_contact()->get_email() ) ) ?></p>
		<p><?php dashicon_e( 'calendar' ); ?> <b><?php _e( 'When' ) ?></b></p>
		<p><abbr
				class="<?php echo $appointment->is_cancelled() ? 'cancelled' : 'scheduled' ?>" title="<?php esc_attr_e( $appointment->get_pretty_start_time( 'admin' ) ); ?>"><?php printf( '%s, %s - %s',
					date_i18n( get_date_format(), $appointment->get_start_time( true ) ),
					date_i18n( get_time_format(), $appointment->get_start_time( true ) ),
					date_i18n( get_time_format(), $appointment->get_end_time( true ) ) ) ?></abbr></p>
		<?php if ( ! $appointment->is_cancelled() ): ?>
		<p><?php echo html()->e( 'a', [
				'href'  => $appointment->manage_link( 'reschedule' ),
				'class' => 'button'
			], dashicon( 'update-alt' ) . __( 'Reschedule', 'groundhogg-calendar' ) );

			echo html()->e( 'a', [
				'href'  => $appointment->manage_link( 'cancel' ),
				'class' => 'button danger'
			], dashicon( 'trash' ) . __( 'Cancel', 'groundhogg-calendar' ) );

			?></p>
		<?php endif; ?>
		<p><?php dashicon_e( 'text' ); ?> <b><?php _e( 'Details' ) ?></b></p>
		<div class="details">
			<?php echo make_clickable( wpautop( $appointment->get_details( false ) ) ); ?>
		</div>
		<p><?php dashicon_e( 'clock' ); ?> <b><?php _e( 'Guest Time Zone' ) ?></b></p>
		<p><?php _e( $appointment->get_contact()->get_time_zone() ); ?></p>
		<p><b><?php _e( 'Add meeting notes' ) ?></b></p>
		<p><?php echo html()->textarea( [
				'name' => 'admin_notes',
				'rows' => 2
			] ) ?></p>
		<p>
			<?php html()->button( [
				'type'  => 'button',
				'text'  => __( 'Update' ),
				'name'  => 'add_meeting_notes',
				'id'    => 'add-meeting-notes',
				'class' => 'button',
				'value' => 'update',
			], true ); ?>
		</p>
	</div>
</div>