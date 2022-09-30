<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Base_Object;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\array_to_css;
use function Groundhogg\do_replacements;
use function Groundhogg\get_array_var;
use function Groundhogg\get_current_contact;
use function Groundhogg\get_default_field_label;
use function Groundhogg\get_post_var;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use function Groundhogg\is_option_enabled;
use function Groundhogg\isset_not_empty;
use function Groundhogg\key_to_words;
use function Groundhogg\managed_page_url;
use function Groundhogg\utils;

/**
 * Template functions for the booking calendar
 */

/**
 * @param $appointment Appointment
 */
function template_cancel( $appointment ) {

	if ( $appointment->is_cancelled() ) {
		template_success( $appointment->get_calendar(), $appointment );

		return;
	}

	?>
	<h4><?php _e( 'Cancel Appointment', 'groundhogg-calendar' ); ?></h4>
	<?php

	cancellation_form();
}

add_action( 'groundhogg/calendar/template/cancel', __NAMESPACE__ . '\template_cancel' );

/**
 * @param $calendar    Calendar
 * @param $appointment Appointment
 */
function template_success( $calendar, $appointment ) {

	$user         = get_userdata( $calendar->get_user_id() );
	$booking_data = get_post_var( 'booking_data', [] );
	$time_zone    = get_array_var( $booking_data, 'time_zone' );

	if ( ! $appointment->is_cancelled() ):
		?>
		<h4><?php _e( 'Confirmed', 'groundhogg-calendar' ); ?></h4>
		<p><?php printf( __( 'You are scheduled with %s %s.' ), $user->first_name, $user->last_name ) ?></p>
		<div class="success-message">
			<?php echo wpautop( do_replacements( $calendar->get_meta( 'message' ), $appointment->get_contact() ) ); ?>
		</div>
		<div class="confirmed-details details">
			<?php
			// Convert to the visitors time zone.
			$start_time = get_in_time_zone( $appointment->get_start_time(), $time_zone );
			$end_time   = get_in_time_zone( $appointment->get_end_time(), $time_zone );

			$time_string = sprintf( '%s - %s, %s', date_i18n( get_time_format(), $start_time ), date_i18n( get_time_format(), $end_time ), date_i18n( get_date_format(), $start_time ) );
			?>
			<div class="details-slot"><span class="date-icon"></span> <?php esc_html_e( $time_string ); ?></div>
			<div class="details-zone"><span
					class="world-icon"></span> <?php esc_html_e( str_replace( '_', ' ', $time_zone ) ); ?></div>
		</div>
		<p><?php printf( __( 'A calendar invitation has been send to %s', 'groundhogg-calendar' ), $appointment->get_contact()->get_email() ); ?></p>
	<?php
	else:
		?>
		<h4><?php _e( 'Cancelled', 'groundhogg-calendar' ); ?></h4>
		<p><?php printf( __( 'Your appointment with %s %s is cancelled.' ), $user->first_name, $user->last_name ) ?></p>
		<p>
			<?php

			echo html()->e( 'a', [
				'href'  => managed_page_url( sprintf( 'calendar/%s/', $calendar->slug ) ),
				'class' => 'confirm-button',
			], __( 'Reschedule?', 'groundhogg-calendar' ) );

			?>
		</p>
	<?php
	endif;
}

/**
 * Output the calendar details
 *
 * @param $calendar Calendar
 */
function template_details( $calendar ) {
	if ( ! $calendar || ! $calendar->exists() ) {
		return;
	}

	$user         = get_userdata( $calendar->get_user_id() );
	$booking_data = get_post_var( 'booking_data', [] );
	$time_zone    = get_array_var( $booking_data, 'time_zone' );

	if ( has_custom_logo() ) :

		$image = wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' );
		$width    = 280;

		// Resize image
		if ( $image[1] > $width ) {
			$aspect_ratio = $width / $image[1];
			$image[1]     = $width;
			$image[2]     = $image[2] * $aspect_ratio;
		}

		$style_atts = [
			'background-image'        => 'url(' . esc_url( $image[0] ) . ')',
			'-webkit-background-size' => absint( $image[1] ) . 'px',
			'background-size'         => absint( $image[1] ) . 'px',
			'height'                  => absint( $image[2] ) . 'px',
			'width'                   => absint( $image[1] ) . 'px',
		];
		?>
		<div id="logo-wrap" class="logo-wrap">
			<div id="logo" class="logo" style="<?php echo array_to_css( $style_atts ) ?>"></div>
		</div>
		<?php echo get_avatar( $user->ID, 80 ); ?>
	<?php else: ?>
		<div class="no-logo">
			<?php echo get_avatar( $user->ID, 80 ); ?>
		</div>
	<?php endif; ?>
	<div class="text-details">
		<h4 class="owner-name"><?php esc_html_e( $user->first_name . ' ' . $user->last_name ); ?></h4>
		<h1 class="calendar-name"><?php esc_html_e( $calendar->get_name() ); ?></h1>
		<div class="details">
			<?php $appt_length_formatted = $calendar->get_appointment_length_formatted(); ?>
			<div class="details-length"><span class="clock-icon"></span> <?php esc_html_e( $appt_length_formatted ); ?>
			</div><?php

			if ( ! empty( $booking_data ) ):

				$start_time = absint( get_array_var( $booking_data, 'start_time' ) );
				$end_time = absint( get_array_var( $booking_data, 'end_time' ) );

				// Convert to the visitors time zone.
				$start_time = get_in_time_zone( $start_time, $time_zone );
				$end_time   = get_in_time_zone( $end_time, $time_zone );

				//compared the length of the start and end time instead of comparing the individual value
				if ( ( $start_time > 0 && $end_time > 0 ) && strlen( $start_time ) >= 10 && strlen( $end_time ) >= 10 ):

					$time_string = sprintf( '%s - %s, %s', date_i18n( get_time_format(), $start_time ), date_i18n( get_time_format(), $end_time ), date_i18n( get_date_format(), $start_time ) );
					?>
					<div class="details-slot"><span class="date-icon"></span> <?php esc_html_e( $time_string ); ?></div>
				<?php

				endif; ?>
				<div class="details-zone"><span
				class="world-icon"></span> <?php esc_html_e( str_replace( '_', ' ', $time_zone ) ); ?></div><?php
			endif;

			?>
		</div>
		<?php if ( isset_not_empty( $booking_data, 'reschedule' ) ):

			$appointment = new Appointment( $booking_data['reschedule'] );

			// Convert to the visitors time zone.
			$start_time = get_in_time_zone( $appointment->get_start_time(), $time_zone );
			$end_time   = get_in_time_zone( $appointment->get_end_time(), $time_zone );

			$time_string = sprintf( '%s - %s, %s', date_i18n( get_time_format(), $start_time ), date_i18n( get_time_format(), $end_time ), date_i18n( get_date_format(), $start_time ) );
			?>
			<div class="former-time details">
				<h4><?php _e( 'Former Time', 'groundhogg-calendar' ); ?></h4>
				<div class="details-slot"><span class="date-icon"></span> <?php esc_html_e( $time_string ); ?></div>
			</div>
		<?php endif; ?>
		<div class="description">
			<?php echo wpautop( $calendar->get_description() ) ?>
		</div>
	</div>
	<?php
}

add_action( 'groundhogg/calendar/template/details', __NAMESPACE__ . '\template_details' );

/**
 * Display the booking calendar html
 *
 * @param $calendar Calendar
 */
function template_date_picker( $calendar ) {
	?>
	<div class="date-picker-wrap">
		<div id="date-picker"></div>
	</div>
	<div id="calendar-time-slots">
		<?php do_action( 'groundhogg/calendar/template/time_slots', $calendar ); ?>
	</div>
	<?php
}

add_action( 'groundhogg/calendar/template/date_picker', __NAMESPACE__ . '\template_date_picker' );

/**
 * Display the slots available for the selected date.
 *
 * @param $calendar Calendar
 */
function template_time_slots( $calendar ) {

	if ( ! $calendar || ! $calendar->exists() ) {
		return;
	}

	$booking_data = get_post_var( 'booking_data', [] );

	$date      = sanitize_text_field( get_array_var( $booking_data, 'date' ) );
	$time_zone = sanitize_text_field( get_array_var( $booking_data, 'time_zone' ) );

	if ( ! $date ) {
		return;
	}

	$date_string = date_i18n( get_date_format(), strtotime( $date ) );

	?>
	<div class="back-button">
		<span class="back-arrow-icon"></span>
	</div>
	<p class="time-slot-select-text"><?php esc_html_e( strtoupper( $date_string ) ); ?></p>
	<div id="time-slots-inner">
		<?php

		$slots = $calendar->get_appointment_slots( $date, $time_zone );

		if ( empty( $slots ) ):
			?><p
			class="gh-message-wrapper gh-form-errors-wrapper"><?php _e( 'Sorry, No time slots are available on this date.', 'groundhogg-calendar' ); ?></p><?php
		else:
			foreach ( $slots as $i => $slot ):

				echo html()->input( [
					'type'            => 'button',
					'class'           => 'appointment-time confirm-button',
					'name'            => 'appointment_time',
					'id'              => 'gh_appointment_' . $i,
					'data-start_date' => $slot['start'],
					'data-end_date'   => $slot['end'],
					'value'           => $slot['display'],
				] );

			endforeach;

		endif;

		?>
	</div>
	<?php
}

add_action( 'groundhogg/calendar/template/time_slots', __NAMESPACE__ . '\template_time_slots' );

/**
 * Output the final form and review page
 *
 * @param $calendar Calendar
 */
function template_form( $calendar ) {

	if ( ! $calendar || ! $calendar->exists() ) {
		return;
	}

	$booking_data = get_post_var( 'booking_data', [] );

	?>
	<div class="back-button">
		<span class="back-arrow-icon"></span>
	</div>

	<?php if ( ! isset_not_empty( $booking_data, 'reschedule' ) ): ?>
		<h3><?php _e( 'Enter Details', 'groundhogg-calendar' ); ?></h3>
		<?php
		if ( $calendar->has_linked_form() ) {

			$shortcode = sprintf( '[gh_form id="%d" class="details-form"]', $calendar->get_linked_form() );

			echo do_shortcode( $shortcode );

		} else {
			default_form();
		}
		?>
	<?php else: ?>
		<h3><?php _e( 'Confirm Change', 'groundhogg-calendar' ); ?></h3>
		<?php reschedule_form(); ?>
	<?php endif;
}

add_action( 'groundhogg/calendar/template/form', __NAMESPACE__ . '\template_form' );

/**
 * The default form to show if no form is provided.
 */
function default_form() {

	$contact = get_current_contact();

	?>
	<div class="gh-form-wrapper">
		<form class="gh-form details-form" method="post" target="_parent">
			<div class="form-fields">
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-2">
						<div class="gh-form-field">
							<label class="gh-input-label">
								<?php

								_e( 'First *', 'groundhogg-calendar' );

								echo html()->input( [
									'type'        => 'text',
									'name'        => 'first_name',
									'id'          => 'first_name',
									'class'       => 'gh-input',
									'placeholder' => __( 'John' ),
									'required'    => true,
									'value'       => $contact ? $contact->get_first_name() : '',
								] );
								?>
							</label>
						</div>
					</div>
					<div class="gh-form-column col-1-of-2">
						<div class="gh-form-field">
							<label class="gh-input-label">
								<?php

								_e( 'Last *', 'groundhogg-calendar' );

								echo html()->input( [
									'type'        => 'text',
									'name'        => 'last_name',
									'id'          => 'last_name',
									'class'       => 'gh-input',
									'placeholder' => __( 'Doe' ),
									'required'    => true,
									'value'       => $contact ? $contact->get_last_name() : '',
								] );
								?>
							</label>
						</div>
					</div>
				</div>
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<label class="gh-input-label">
								<?php

								_e( 'Email *', 'groundhogg-calendar' );

								echo html()->input( [
									'type'        => 'email',
									'name'        => 'email',
									'id'          => 'email',
									'class'       => 'gh-input',
									'placeholder' => __( 'name@example.com' ),
									'required'    => true,
									'value'       => $contact ? $contact->get_email() : '',
								] );
								?>
							</label>
						</div>
					</div>
				</div>
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<label class="gh-input-label">
								<?php

								_e( 'Phone *', 'groundhogg-calendar' );

								echo html()->input( [
									'type'        => 'tel',
									'name'        => 'phone',
									'id'          => 'phone',
									'class'       => 'gh-input',
									'placeholder' => __( '+1 (555) 555-5555', 'groundhogg-calendar' ),
									'required'    => true,
									'value'       => $contact ? $contact->get_phone_number() : '',
								] );
								?>
							</label>
						</div>
					</div>
				</div>
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<label class="gh-input-label">
								<?php

								_e( 'Please share anything that will help prepare for our meeting.', 'groundhogg-calendar' );

								echo html()->textarea( [
									'name'     => 'additional',
									'id'       => 'additional',
									'class'    => 'gh-input',
									'required' => false,
									'rows'     => 2,
								] );
								?>
							</label>
						</div>
					</div>
				</div>
				<?php if ( is_option_enabled( 'gh_enable_gdpr' ) ) : ?>
					<div class="gh-form-row clearfix">
						<div class="gh-form-column col-1-of-1">
							<div class="gh-form-field">
								<p>
									<?php echo html()->checkbox( [
										'label' => get_default_field_label( 'gdpr_consent' ),
										'name'  => 'gdpr_consent',
										'id'    => 'gdpr_consent',
										'class' => 'gh-gdpr',
										'value' => 'yes',
										'title' => _x( 'I Consent', 'form_default', 'groundhogg' ),
									] ) ?>
								</p>
								<p>
									<?php echo html()->checkbox( [
										'label' => get_default_field_label( 'marketing_consent' ),
										'name'  => 'marketing_consent',
										'id'    => 'marketing_consent',
										'class' => 'gh-gdpr',
										'value' => 'yes',
										'title' => _x( 'I Consent', 'form_default', 'groundhogg' ),
									] ) ?>
								</p>
							</div>
						</div>
					</div>
				<?php endif; ?>
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<?php
							$book_text = apply_filters( 'groundhogg/calendar/shortcode/confirm_text', __( 'Book Appointment', 'groundhogg-calendar' ) );
							echo html()->input( [
								'type'  => 'submit',
								'class' => 'confirm-button',
								'name'  => 'book_appointment',
								'id'    => 'book_appointment',
								'value' => $book_text
							] ); ?>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
	<?php
}

function cancellation_form() {
	?>
	<div class="gh-form-wrapper">
		<form class="gh-form details-form" method="post" target="_parent">
			<div class="form-fields">
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<label class="gh-input-label">
								<?php

								_e( 'Reason for cancelling?', 'groundhogg-calendar' );

								echo html()->textarea( [
									'name'     => 'reason',
									'id'       => 'reason',
									'class'    => 'gh-input',
									'required' => false,
									'rows'     => 2,
								] );
								?>
							</label>
						</div>
					</div>
				</div>
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<?php
							$cancel = __( 'Cancel', 'groundhogg-calendar' );
							echo html()->input( [
								'type'  => 'submit',
								'name'  => 'cancel',
								'id'    => 'cancel',
								'class' => 'confirm-button',
								'value' => $cancel
							] ); ?>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
	<?php
}

function reschedule_form() {
	?>
	<div class="gh-form-wrapper">
		<form class="gh-form details-form" method="post" target="_parent">
			<div class="form-fields">
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<label class="gh-input-label">
								<?php

								_e( 'Reason for rescheduling?', 'groundhogg-calendar' );

								echo html()->textarea( [
									'name'     => 'reason',
									'id'       => 'reason',
									'class'    => 'gh-input',
									'required' => false,
									'rows'     => 2,
								] );
								?>
							</label>
						</div>
					</div>
				</div>
				<div class="gh-form-row clearfix">
					<div class="gh-form-column col-1-of-1">
						<div class="gh-form-field">
							<?php
							echo html()->input( [
								'type'  => 'submit',
								'name'  => 'reschedule',
								'id'    => 'reschedule',
								'class' => 'confirm-button',
								'value' => __( 'Reschedule', 'groundhogg-calendar' )
							] ); ?>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
	<?php
}
