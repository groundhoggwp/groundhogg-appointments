<?php

use function Groundhogg\dashicon;
use function Groundhogg\get_db;
use function Groundhogg\admin_page_url;
use GroundhoggBookingCalendar\Classes\Appointment;
use function Groundhogg\html;
use function GroundhoggBookingCalendar\get_date_format;
use function GroundhoggBookingCalendar\get_time_format;

/**
 * Display formatting examples for the new css design system for the sidebar info cards
 *
 * @var $contact \Groundhogg\Contact
 */

$appointments = get_db( 'appointments' )->query( [
	'orderby' => 'start_time',
	'order'   => 'desc'
] );

/**
 * @param $status
 * @param $start_time
 * @param $end_time
 */
function status_label( $status, $start_time, $end_time ) {

	if ( $status !== 'cancelled' ){
		if ( $start_time < time() && $end_time > time() ) {
			$status = 'in_progress';
		} else if ( $end_time < time() ) {
			$status = 'past';
		}
	}

	switch ( $status ):
		case 'scheduled':
			$color = 'green';
			$label = __( 'Scheduled', 'groundhogg-calendar' );
			break;
		case 'cancelled':
			$color = 'red';
			$label = __( 'Cancelled', 'groundhogg-calendar' );
			break;
		case 'past':
		default:
			$label = __( 'Finished', 'groundhogg-calendar' );
			$color = 'orange';
			break;
	endswitch;
	?>
	<span class="<?php echo $color ?>"><?php echo $label; ?></span>
	<?php
}


if ( empty( $appointments ) ):?>
	<p><?php _e( 'No appointments yet.', 'groundhogg-calendar' ); ?></p>
<?php else: ?>

	<?php foreach ( $appointments as $appointment ):
		$appointment = new Appointment( $appointment );
		?>
		<div class="ic-section">
			<div class="ic-section-header">
				<div class="ic-section-header-content">
					<div class="basic-details">
						<a href="<?php echo esc_url( admin_page_url( 'gh_appointments', [
							'appointment' => $appointment->get_id()
						] ) ); ?> ">#<?php echo $appointment->get_id(); ?></a> <?php status_label( $appointment->get_status(), $appointment->get_start_time(), $appointment->get_end_time() ); ?>
					</div>
				</div>
			</div>
			<div class="ic-section-content">
				<ul class="info-list">
					<li>
						<abbr
								class="<?php echo $appointment->is_cancelled() ? 'cancelled' : 'scheduled' ?>"
								title="<?php esc_attr_e( $appointment->get_pretty_start_time( 'admin' ) ); ?>"><?php printf( '%s, %s - %s',
									date_i18n( get_date_format(), $appointment->get_start_time( true ) ),
									date_i18n( get_time_format(), $appointment->get_start_time( true ) ),
									date_i18n( get_time_format(), $appointment->get_end_time( true ) ) ) ?></abbr>
					</li>
					<?php if ( ! $appointment->is_cancelled() ): ?>
						<li style="margin-top: 10px"><?php echo html()->e( 'a', [
								'href'  => $appointment->manage_link( 'reschedule' ),
								'class' => 'no-underline'
							], dashicon( 'update-alt' ) . __( 'Reschedule', 'groundhogg-calendar' ) );
							echo ' | ';
							echo html()->e( 'a', [
								'href'  => $appointment->manage_link( 'cancel' ),
								'class' => 'danger no-underline'
							], dashicon( 'trash' ) . __( 'Cancel', 'groundhogg-calendar' ) );

							?></li>
					<?php endif; ?>
				</ul>
			</div>
		</div>
	<?php endforeach; ?>
<?php endif;
