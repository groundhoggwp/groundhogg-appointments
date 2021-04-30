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
		<tr class="form-field term-contact-wrap">
			<th scope="row"><label><?php _e( 'Calendar Owner' ) ?></label></th>
			<td>
				<?php echo html()->dropdown_owners( [
					'selected' => ( $calendar->user_id ) ? $calendar->user_id : 0
				] ); ?>
				<p class="description"><?php _e( 'Select owner for whom you are creating the calendar.', 'groundhogg-calendar' ) ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label><?php _e( 'Calendar Description', 'groundhogg-calendar' ); ?></label></th>
			<td>
				<?php echo html()->textarea( [
					'name'        => 'description',
					'class'       => '',
					'cols'        => 60,
					'placeholder' => __( 'Calendar Description', 'groundhogg-calendar' ),
					'value'       => $calendar->get_description()
				] ); ?>
				<p class="description"><?php _e( 'Describe your booking calendar in few words. Visible to all users.', 'groundhogg-calendar' ) ?></p>
			</td>
		</tr>
		</tbody>
	</table>
	<h2><?php _e( 'Appointment Settings', 'groundhogg-calendar' ); ?></h2>
	<table class="form-table">
		<tbody>
		<tr>
			<th><?php _e( 'Default Name' ); ?></th>
			<td>
				<p><?php Plugin::instance()->replacements->show_replacements_dropdown(); ?></p>
				<?php echo html()->input( [
					'name'        => 'default_name',
					'placeholder' => __( 'Default appointment name.', 'groundhogg-calendar' ),
					'value'       => $calendar->get_meta( 'default_name' ) ? $calendar->get_meta( 'default_name' ) : ''
				] );
				echo html()->description( __( 'This is the default name of the appointment that shows in the list.', 'groundhogg-calendar' ) );
				?>
			</td>
		</tr>
		<tr>
			<th><?php _e( 'Default Description', 'groundhogg-calendar' ); ?></th>
			<td>
				<p><?php Plugin::instance()->replacements->show_replacements_dropdown(); ?></p>
				<?php echo html()->textarea( [
					'class'       => '',
					'cols'        => 60,
					'name'        => 'default_note',
					'placeholder' => __( 'Default notes for all appointments.', 'groundhogg-calendar' ),
					'value'       => $calendar->get_meta( 'default_note' ) ? $calendar->get_meta( 'default_note' ) : ''
				] );
				echo html()->description( __( 'This default description will be used for email notifications sent to admins and the contact.', 'groundhogg-calendar' ) );
				?>
			</td>
		</tr>
		<!--		<tr>-->
		<!--			<th scope="row"><label>--><?php //_e( 'Show in 12 hour format', 'groundhogg-calendar' )
		?><!--</label></th>-->
		<!--			<td>-->
		<!--				--><?php //echo html()->checkbox( [
		//					'label'   => "Enable",
		//					"name"    => "time_12hour",
		//					'checked' => $calendar->show_in_12_hour() ? $calendar->show_in_12_hour() : 0,
		//				] );
		?>
		<!--				<p class="description">-->
		<?php //_e( 'Enabling this setting displays time in 12 hour format. (e.g 5:00 PM)', 'groundhogg-calendar' )
		?><!--</p>-->
		<!--			</td>-->
		<!--		</tr>-->
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
		<?php

		add_action( 'media_buttons', [
			\Groundhogg\Plugin::$instance->replacements,
			'show_replacements_dropdown'
		] );

		wp_editor( $calendar->get_meta( 'message' ) ? $calendar->get_meta( 'message' ) : __( 'Appointment booked Successfully!', 'groundhogg-calendar' ), 'message', [
			'editor_height' => 200,
			'editor_width'  => 500
		] ); ?>
	</div>
	<?php submit_button( __( 'Update Calendar' ), 'primary', 'update' ); ?>
</form>