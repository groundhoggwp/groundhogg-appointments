<?php

namespace GroundhoggBookingCalendar;

use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\get_array_var;
use function Groundhogg\get_current_contact;
use function Groundhogg\get_post_var;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use function Groundhogg\isset_not_empty;
use function Groundhogg\key_to_words;

/**
 * Template functions for the booking calendar
 */

/**
 * Output the calendar details
 *
 * @param $calendar Calendar
 */
function template_details( $calendar ) {
	if ( ! $calendar || ! $calendar->exists() ) {
		return;
	}

	$booking_data = get_post_var( 'booking_data', [] );

	?>
    <h4><?php esc_html_e( get_bloginfo( 'name' ) ); ?></h4>
    <h1><?php esc_html_e( $calendar->get_name() ); ?></h1>
    <div class="details">
		<?php

		$appt_length_formatted = $calendar->get_appointment_length_formatted();

		?>
        <div class="details-length"><span class="clock-icon"></span> <?php esc_html_e( $appt_length_formatted ); ?>
        </div><?php

		if ( ! empty( $booking_data ) ):

			$start_time = absint( get_array_var( $booking_data, 'start_time' ) );
			$end_time = absint( get_array_var( $booking_data, 'end_time' ) );
			$time_zone = str_replace( ' ', '_', key_to_words( sanitize_text_field( trim( get_array_var( $booking_data, 'time_zone' ) ) ) ) );

			// Convert to the visitors time zone.
			$start_time = get_in_time_zone( $start_time, $time_zone );
			$end_time   = get_in_time_zone( $end_time, $time_zone );

			//compared the length of the start and end time instead of comparing the individual value
			if ( ($start_time > 0 && $end_time > 0 ) && strlen($start_time)>=10  && strlen( $end_time) >= 10 ):

			$time_string = sprintf( '%s - %s, %s', date_i18n( get_time_format(), $start_time ), date_i18n( get_time_format(), $end_time ), date_i18n( get_date_format(), $start_time ) );
				?>
                <div class="details-slot"><span class="date-icon"></span> <?php esc_html_e( $time_string ); ?></div>
              <?php

			endif; ?>
            <div class="details-zone"><span class="world-icon"></span> <?php esc_html_e( str_replace( '_', ' ', $time_zone ) ); ?></div><?php
		endif;

		?>
    </div>
    <p><?php esc_html_e( $calendar->get_description() ); ?></p>
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
    <p class="time-slot-select-text"><?php esc_html_e( $date_string ); ?></p>
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
					'class'           => 'appointment-time',
					'name'            => 'appointment_time',
					'id'              => 'gh_appointment_' . $i,
					'data-start_date' => $slot[ 'start' ],
					'data-end_date'   => $slot[ 'end' ],
					'value'           => $slot[ 'display' ],
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
	<?php else: ?>
        <h3><?php _e( 'Confirm Change', 'groundhogg-calendar' ); ?></h3>
	<?php endif;

	if ( isset_not_empty( $booking_data, 'reschedule' ) ) {

		$appointment_id = absint( get_array_var( $booking_data, 'reschedule' ) );

		?>
        <form class="gh-form details-form" method="post" target="_parent">
		<?php

		echo html()->input( [
			'type'  => 'hidden',
			'name'  => 'appointment',
			'value' => $appointment_id
		] );

		echo html()->input( [
			'type'  => 'hidden',
			'name'  => 'event',
			'value' => 'reschedule'
		] );
		?><p></p><?php
		echo html()->button( [
			'type'  => 'submit',
			'text'  => __( 'Reschedule', 'groundhogg-calendar' ),
			'name'  => 'reschedule',
			'id'    => 'reschedule',
			'class' => 'button',
			'value' => 'reschedule',
		] );

		?>
        </form><?php

		return;
	}

	if ( $calendar->has_linked_form() ) {

		$shortcode = sprintf( '[gh_form id="%d" class="details-form"]', $calendar->get_linked_form() );

		echo do_shortcode( $shortcode );

		return;
	}

	default_form();

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
            <div class="gh-form">
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
							<?php
							$book_text = apply_filters( 'groundhogg/calendar/shortcode/confirm_text', __( 'Book Appointment', 'groundhogg-calendar' ) );
							echo html()->input( [
								'type'  => 'submit',
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