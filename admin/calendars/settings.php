<?php
namespace GroundhoggBookingCalendar\Admin\Calendars;

use Groundhogg\Plugin;
use function Groundhogg\get_form_list;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$calendar_id = intval( $_GET['calendar'] );
$calendar    = new Calendar( $calendar_id );
if ( $calendar == null ) {
	wp_die( __( 'Calendar not found.', 'groundhogg-calendar' ) );
}
?>
<form name="" id="" method="post" action="">
	<?php wp_nonce_field(); ?>
	<h3><?php _e( 'Calendar Settings', 'groundhogg-calendar' ) ?></h3>
	<table class="form-table">
		<tbody>
		<tr>
			<th scope="row"><label><?php _e( 'Calendar Name' ) ?></label></th>
			<td>
				<?php echo html()->input( [
					'name'        => 'name',
					'placeholder' => __( 'Calendar Name', 'groundhogg-calendar' ),
					'value'       => $calendar->get_name()
				] ); ?>
				<p class="description"><?php _e( 'A name of a calendar.', 'groundhogg-calendar' ) ?>.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'URL Slug' ) ?></label></th>
			<td>
				<?php echo html()->input( [
					'name'        => 'slug',
					'placeholder' => sanitize_title( $calendar->get_name() ),
					'value'       => $calendar->slug
				] ); ?>
				<p class="description"><?php _e( 'The public URL of the calendar.', 'groundhogg-calendar' ) ?>.</p>
			</td>
		</tr>
		<tr class="form-field term-contact-wrap">
			<th scope="row"><label><?php _e( 'Calendar Owner' ) ?></label></th>
			<td>
				<?php echo html()->dropdown_owners( [
					'selected' => $calendar->get_user_id() ?: 0
				] ); ?>
				<p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg-calendar' ) ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'Description/Instructions', 'groundhogg-calendar' ); ?></label></th>
			<td>
				<div style="max-width: 700px">
					<?php wp_editor( $calendar->get_description(), 'description', [
						'editor_height' => 200,
						'editor_width'  => 300,
						'media_buttons' => false,
						'quicktags'     => false,
					] ); ?>
					<p class="description"><?php _e( 'Describe your booking calendar in few words and any instructions for the guest. Shows on the scheduling page as well as in the appointment description.', 'groundhogg-calendar' ) ?></p>
				</div>
			</td>
		</tr>
		</tbody>
	</table>
	<h2><?php _e( 'Appointment Settings', 'groundhogg-calendar' ); ?></h2>
	<table class="form-table">
		<tbody>
		<tr>
			<th><?php _e( 'Additional Instructions/Notes', 'groundhogg-calendar' ); ?></th>
			<td>
				<p><?php Plugin::instance()->replacements->show_replacements_dropdown(); ?></p>
				<div style="max-width: 700px">
					<?php wp_editor( $calendar->get_meta( 'additional_notes' ), 'additional_notes', [
						'editor_height' => 200,
						'editor_width'  => 500,
						'media_buttons' => false,
						'quicktags'     => false,
					] ); ?>
				</div>
				<?php
				echo html()->description( __( 'Any additional instructions you want to add after the appointment has been booked.', 'groundhogg-calendar' ) );
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'Length of appointment', 'groundhogg-calendar' ); ?></label></th>
			<td>
				<?php
				for ( $i = 0; $i < 24; $i ++ ) {
					$hours[ $i ] = $i;
				}
				for ( $i = 0; $i < 60; $i ++ ) {
					$mins[ $i ] = $i;
				}

				echo html()->dropdown( [
					'name'     => 'slot_hour',
					'options'  => $hours,
					'selected' => $calendar->get_meta( 'slot_hour', true ) ? $calendar->get_meta( 'slot_hour', true ) : 0,
				] );
				echo "&nbsp;";
				_e( 'Hour(s)', 'groundhogg-calendar' );
				echo "&nbsp;";
				echo html()->dropdown( [
					'name'     => 'slot_minute',
					'options'  => $mins,
					'selected' => $calendar->get_meta( 'slot_minute', true ) ? $calendar->get_meta( 'slot_minute', true ) : 0,
				] );
				echo "&nbsp;";
				_e( 'Minutes', 'groundhogg-calendar' );
				?>
				<p class="description"><?php _e( 'Select default length of appointment', 'groundhogg-calendar' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'Buffer Time', 'groundhogg-calendar' ) ?></label></th>
			<td>
				<?php
				for ( $i = 0; $i <= 60; $i ++ ) {
					$mins[ $i ] = $i;
				}
				echo html()->dropdown( [
					'name'     => 'buffer_time',
					'options'  => $mins,
					'selected' => $calendar->get_meta( 'buffer_time', true ) ? $calendar->get_meta( 'buffer_time', true ) : 0
				] );
				echo "&nbsp;";
				_e( 'Minutes', 'groundhogg-calendar' ); ?>
				<p class="description"><?php _e( 'Add extra time between appointments.', 'groundhogg-calendar' ) ?></p>
			</td>
		</tr>
		</tbody>
	</table>
	<h3><?php _e( 'Submission Settings', 'groundhogg-calendar' ) ?></h3>
	<table class="form-table">
		<tbody>
		<tr>
			<th><?php _e( 'Use a custom form', 'groundhogg-calendar' ); ?></th>
			<td>
				<?php echo html()->dropdown( [
					'options'  => get_form_list(),
					'name'     => 'override_form_id',
					'id'       => 'override_form_id',
					'selected' => absint( $calendar->get_meta( 'override_form_id' ) )
				] );

				echo html()->description( __( 'Use a custom form built using the form builder in a funnel instead of the default form.', 'groundhogg-calendar' ) );
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'Redirect to another page', 'groundhogg-calendar' ) ?></label></th>
			<td>
				<?php echo html()->link_picker( [
					'name'        => 'redirect_link',
					'placeholder' => site_url(),
					'value'       => $calendar->get_meta( 'redirect_link', true )
				] ); ?>
				<p>
					<?php echo html()->checkbox( [
						'label'   => 'Enable',
						'name'    => 'redirect_link_status',
						'checked' => $calendar->get_meta( 'redirect_link_status', true ) ? $calendar->get_meta( 'redirect_link_status', true ) : 0
					] );
					?>
				</p>
				<p class="description"><?php _e( 'Enabling this setting redirect user to specified thank you page.', 'groundhogg-calendar' ) ?></p>
			</td>
		</tr>
		</tbody>
	</table>
	<h2><?php _e( 'Success Message', 'groundhogg-calendar' ); ?></h2>
	<div style="max-width: 700px">
		<p><?php Plugin::instance()->replacements->show_replacements_dropdown(); ?></p>
		<?php wp_editor( $calendar->get_meta( 'message' ) ? $calendar->get_meta( 'message' ) : __( 'Appointment booked Successfully!', 'groundhogg-calendar' ), 'message', [
			'editor_height' => 200,
			'editor_width'  => 500,
			'media_buttons' => false,
			'quicktags'     => false,
		] ); ?>
	</div>
	<?php submit_button( __( 'Update Calendar' ), 'primary', 'update' ); ?>
</form>