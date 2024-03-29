<?php
namespace GroundhoggBookingCalendar\Admin\Calendars;

use Groundhogg\Plugin;
use function Groundhogg\action_url;
use function Groundhogg\array_map_keys;
use function Groundhogg\array_map_with_keys;
use function Groundhogg\get_db;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;
use function GroundhoggBookingCalendar\zoom;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$calendar_id = intval( $_GET['calendar'] );
$calendar    = new Calendar( $calendar_id );
if ( $calendar == null ) {
	wp_die( __( 'Calendar not found.', 'groundhogg-calendar' ) );
}

$connect_to_google_url = action_url( 'access_code', [
	'calendar' => $calendar_id
] );

$connect_to_zoom_url = action_url( 'access_code_zoom', [
	'calendar' => $calendar_id
] );

$google_connection = $calendar->get_google_connection();

$connections = get_db( 'google_connections' )->query();

?>
<form method="post">
	<?php wp_nonce_field(); ?>
	<h2><?php _e( 'Google Calendar Integration', 'groundhogg-calendar' ); ?></h2>
	<table class="form-table">
		<tbody>
		<tr>
			<th scope="row"><label><?php _e( 'Select Account', 'groundhogg-calendar' ) ?></label></th>
			<td id="google">
				<?php if ( ! empty( $connections ) ) : ?>
					<p>
						<?php _e( 'Choose an existing Google Calendar integration', 'groundhogg-calendar' ); ?>
						<?php echo html()->dropdown( [
							'options'     => array_map_with_keys( array_map_keys( $connections, function ( $i, $v ) {
								return $v->ID;
							} ), function ( $v, $i ) {
								return sprintf( '%s (%s)', $v->account_email, $v->status === 'active' ? __( 'Active' ) : __( 'Inactive' ) );
							} ),
							'name'        => 'google_account_id',
							'selected'    => $calendar->get_google_connection_id(),
							'option_none' => __( 'No connection', 'groundhogg-calendar' )
						] ); ?>
						<?php printf( __( 'or <a href="%s">connect to another Google account.</a>', 'groundhogg-calendar' ), esc_url( $connect_to_google_url ) ); ?>
					</p>
				<?php else: ?>
					<p>
						<a class="button" id="generate_access_code_zoom" target="_blank"
						   href="<?php echo esc_url( $connect_to_google_url ); ?>"><?php _e( 'Connect to your Google account!', 'groundhogg-calendar' ); ?></a>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $google_connection->exists() ) : ?>
			<tr>
				<th scope="row"><label><?php _e( 'Use for appointments' ) ?></label></th>
				<td id="appointments">
					<p>
						<?php

						echo html()->dropdown( [
							'options'     => array_map_with_keys( array_map_keys( $google_connection->get_calendars(), function ( $i, $v ) {
								return $v->get_id();
							} ), function ( $v, $i ) {
								return $v->name;
							} ),
							'selected'    => $calendar->get_local_google_calendar_id(),
							'name'        => 'google_calendar_id',
							'option_none' => __( 'Please select a calendar', 'groundhogg-calendar' ),
						] );

						?>
					</p>
					<p class="description"><?php _e( 'Select which calendar appointments should be added to.', 'groundhogg-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php _e( 'Use for availability' ) ?></label></th>
				<td id="availability">
					<p class="description"><?php _e( 'These calendars will be checked for your availability when booking new appointments.' ); ?></p>
					<p>
						<?php

						foreach ( $google_connection->get_calendars() as $google_calendar ) {
							echo html()->checkbox( array(
								'name'     => 'google_calendar_list[]',
								'value'    => $google_calendar->get_id(),
								'label'    => $google_calendar->name,
								'checked'  => in_array( $google_calendar->get_id(), $calendar->get_google_calendar_list() ) || $calendar->get_local_google_calendar_id() === $google_calendar->get_id(),
								'disabled' => $calendar->get_local_google_calendar_id() === $google_calendar->get_id(),
							) );
							echo '<br/>';
						}

						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php _e( 'Create a Google Meet room', 'groundhogg-calendar' ) ?></label></th>
				<td>
					<?php echo html()->checkbox( [
						'label'   => "Enable",
						"name"    => "google_meet_enable",
						'checked' => $calendar->is_google_meet_enabled() ? $calendar->is_google_meet_enabled() : 0,
					] ); ?>
					<p class="description"><?php _e( 'Enabling this setting will create Google Meet meeting when appointment is booked.', 'groundhogg-calendar' ) ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label><?php _e( 'Override appointment name in Google', 'groundhogg-calendar' ) ?></label></th>
				<td>
					<p><?php Plugin::$instance->replacements->show_replacements_dropdown(); ?></p>
					<?php
					echo html()->input( [
						'type'        => 'text',
						'name'        => 'google_appointment_name',
						'value'       => $calendar->get_meta( 'google_appointment_name', true ),
						'placeholder' => $calendar->get_meta( 'default_name' ),
					] );
					?>

					<p class="description"><?php _e( 'This will override the regular appointment name in your Google calendar. Leave empty if no change is required.', 'groundhogg-calendar' ); ?></p>
					<p class="description"><?php _e( 'The original fomart for the name is <b>Contact Name and Owner Name</b>.', 'groundhogg-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php _e( 'Additional details for Google' ) ?></label></th>
				<td>
					<p><?php Plugin::$instance->replacements->show_replacements_dropdown(); ?></p>
					<div style="max-width: 700px">
						<?php wp_editor( $calendar->get_meta( 'google_appointment_description' ), 'google_appointment_description', [
							'editor_height' => 200,
							'editor_width'  => 500,
							'media_buttons' => false,
							'quicktags'     => false,
						] ); ?>
					</div>
					<p class="description"><?php _e( 'These details will be added to the regular appointment notes in your Google calendar.', 'groundhogg-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label><?php _e( 'Location', 'groundhogg-calendar' ) ?></label></th>
				<td>
					<?php echo html()->input( [
						'name'        => 'appointment_location',
						'value'       => $calendar->get_meta( 'appointment_location' ),
						'placeholder' => '{business_address}'
					] ) ?>
					<p class="description"><?php _e( 'Provide a location for the appointment. This will be overridden by the Zoom meeting URL if the Zoom integration is enabled.', 'groundhogg-calendar' ) ?></p>
				</td>
			</tr>
		<?php endif; ?>
		</tbody>
	</table>
	<h2><?php _e( 'Zoom Integration', 'groundhogg-calendar' ); ?></h2>
	<table class="form-table">
		<tbody>
		<tr>
			<th scope="row"><label><?php _e( 'Zoom Connection', 'groundhogg-calendar' ) ?></label></th>
			<td id="zoom">
				<?php if ( count( zoom()->get_connections() ) > 0 ) : ?>
					<p>
						<?php _e( 'Choose an existing Zoom integration', 'groundhogg-calendar' ); ?>
						<?php echo html()->dropdown( [
							'options'     => zoom()->get_connections_for_dropdown(),
							'name'        => 'zoom_account_id',
							'selected'    => $calendar->get_zoom_account_id(),
							'option_none' => __( 'No connection', 'groundhogg-calendar' )
						] ); ?>
						<?php _e( 'or', 'groundhogg-calendar' ); ?>
						<a class="button" id="generate_access_code_zoom" target="_blank"
						   href="<?php echo esc_url( $connect_to_zoom_url ) ?>"><?php _e( 'Connect to another Zoom account!', 'groundhogg-calendar' ); ?></a>
					</p>
				<?php else: ?>
					<p>
						<a class="button" id="generate_access_code_zoom" target="_blank"
						   href="<?php echo esc_url( $connect_to_zoom_url ); ?>"><?php _e( 'Connect to your Zoom account!', 'groundhogg-calendar' ); ?></a>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		</tbody>
	</table>
	<?php submit_button( __( 'Save changes' ), 'primary', 'update' ); ?>
</form>