<?php

use function Groundhogg\get_db;
use function Groundhogg\admin_page_url;
use Groundhogg\Plugin;
use function Groundhogg\get_date_time_format;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;

/**
 * Display formatting examples for the new css design system for the sidebar info cards
 *
 * @var $contact \Groundhogg\Contact
 */
// fetch upcoming appointments
$where        = [
	'relationship' => 'AND',
	[ 'col' => 'start_time', 'val' => absint( time() ), 'compare' => '>' ],
	[ 'col' => 'contact_id', 'val' => $contact->get_id(), 'compare' => '=' ],
];
$order        = 'start_time';
$args         = array(
	'where' => $where,
	'order' => $order
);
$appointments = get_db( 'appointments' )->query( $args );
// fetch past appointments
$where             = [
	'relationship' => 'AND',
	[ 'col' => 'start_time', 'val' => absint( time() ), 'compare' => '<' ],
	[ 'col' => 'contact_id', 'val' => $contact->get_id(), 'compare' => '=' ],
];
$order             = 'start_time';
$args              = array(
	'where'   => $where,
	'order'   => 'desc',
	'orderby' => 'end_time'
);
$past_appointments = get_db( 'appointments' )->query( $args );

/**
 * @param $status
 *
 * @return string
 */
function get_status( $status ) {
	switch ( $status ):
		case 'approved':
			$color = 'green';
			break;
		case 'canceled':
			$color = 'red';
			break;
		case 'pending':
		default:
			$color = 'orange';
			break;
	endswitch;
	?>
	<span class="<?php echo $color ?>"><?php echo ucfirst( $status ); ?></span>
	<?php
}

/**
 * @param $appointment
 *
 * @return string
 */
function appointment_start_end( $appointment ) {
	$start_time = date( get_date_time_format(), Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_start_time() ) );
	$end_time   = date( get_date_time_format(), Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_end_time() ) );
	echo sprintf( "<b>%s</b> To <b>%s</b>", $start_time, $end_time );
}

/**
 * @param $time
 *
 * @return string
 */
function minutes( $time ) {
	$time = explode( ':', $time );

	return ( $time[0] * 60 ) + ( $time[1] ) + ( $time[2] / 60 ) . ' Minutes';
}

if ( empty( $appointments ) ):?>
	<p><?php _e( 'No appointment yet.', 'groundhogg-calendar' ); ?></p>
<?php else: ?>
	<div class="appointment-section">
		<div class="ic-section">
			<div class="ic-section-header">
				<div class="ic-section-header-content">
					<span class="dashicons dashicons-list-view"></span>
					<?php echo sprintf( "Upcoming Appointments (%s)", count( $appointments ) ); ?>
				</div>
			</div>
			<div class="ic-section-content">
				<?php if ( ! empty( $appointments ) ): ?>
					<?php foreach ( $appointments as $appointment ):
						$appointment = new Appointment( $appointment->ID );
						$calendar = new Calendar( $appointment->data['calendar_id'] );
						?>
						<div class="ic-section">
							<div class="ic-section-header">
								<div class="ic-section-header-content">
									<div class="basic-details">
										<a href="<?php echo esc_url( admin_page_url( 'gh_calendar', [ 'action'      => 'edit_appointment',
										                                                              'appointment' => $appointment->get_id()
										] ) ); ?> ">#<?php echo $appointment->get_id(); ?> </a>
										<?php get_status( $appointment->get_status() ); ?>

									</div>
								</div>

							</div>

							<div class="ic-section-content">
								<span><?php appointment_start_end( $appointment ); ?> </span>
								<span><?php echo ucfirst( $appointment->get_name() ); ?></span>
								<span><?php echo ucfirst( $calendar->data['name'] ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php
				else: _e( 'There is no any upcoming appointment yet!!', 'groundhogg-calendar' ); endif; ?>
			</div>
		</div>
		<div class="ic-section">
			<div class="ic-section-header">
				<div class="ic-section-header-content">
					<span class="dashicons dashicons-list-view"></span>
					<?php echo sprintf( "Past Appointments (%s)", count( $past_appointments ) ); ?>
				</div>
			</div>
			<div class="ic-section-content">
				<?php if ( ! empty( $past_appointments ) ): ?>
					<?php foreach ( $past_appointments as $appointment ):
						$appointment = new Appointment( $appointment->ID );
						$calendar = new Calendar( $appointment->data['calendar_id'] );

						?>
						<div class="ic-section">
							<div class="ic-section-header">
								<div class="ic-section-header-content">
									<div class="basic-details">
										<a href="<?php echo esc_url( admin_page_url( 'gh_calendar', [ 'action'      => 'edit_appointment',
										                                                              'appointment' => $appointment->get_id()
										] ) ); ?> ">#<?php echo $appointment->get_id(); ?> </a>
										<?php get_status( $appointment->get_status() ); ?>

									</div>
								</div>

							</div>
							<div class="ic-section-content">
								<span><?php appointment_start_end( $appointment ); ?> </span>
								<span><?php echo ucfirst( $appointment->get_name() ); ?></span>
								<span><?php echo ucfirst( $calendar->data['name'] ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				<?php
				else: _e( 'There is no any past appointment yet!!', 'groundhogg-calendar' ); endif; ?>
			</div>
		</div>
	</div>
<?php endif;
