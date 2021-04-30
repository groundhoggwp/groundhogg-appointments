<?php

namespace GroundhoggBookingCalendar\Admin\Cards;

use Groundhogg\Contact;
use GroundhoggBookingCalendar\Classes\Appointment;
use function Groundhogg\admin_page_url;
use function Groundhogg\get_db;

//use GroundhoggBookingCalendar\Admin\Appointments;
use GroundhoggBookingCalendar\Admin\Appointments\Appointments_Table;


class appointment_card {

	/**
	 * Buddy_Boss_Info_Card constructor.
	 *
	 * @param \Groundhogg\Admin\Contacts\Info_Cards $cards
	 */
	public function __construct( $cards ) {

		$cards::register( 'appointment-info-card', __( 'Appointments', 'groundhogg-appointment' ), [
			$this,
			'callback'
		] );

	}

	public function get_status( $status ) {
		switch ( $status ):
			case 'scheduled':
				$color = 'green';
				break;
			case 'cancelled':
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
	 * @param $contact Contact
	 */
	public function callback( $contact ) {
		$where_future = [
			'relationship' => 'AND',
			[ 'col' => 'start_time', 'val' => absint( time() ), 'compare' => '>' ],
			[ 'col' => 'contact_id', 'val' => $contact->get_id(), 'compare' => '=' ],
		];

		$appointments = get_db( 'appointments' )->query( [
			'where' => $where_future
		] );


		$where_past = [
			'relationship' => 'AND',
			[ 'col' => 'start_time', 'val' => absint( time() ), 'compare' => '<' ],
			[ 'col' => 'contact_id', 'val' => $contact->get_id(), 'compare' => '=' ],
		];


		$past_appointments = get_db( 'appointments' )->query( [
			'where' => $where_past
		] );


		//print_r( $appointments );

		?>
		<div class="appointment-section">

			<div class="ic-section">
				<div class="ic-section-header">
					<div class="ic-section-header-content">
						<span class="dashicons dashicons-list-view"></span>
						<?php _e( 'Upcoming Appointment', 'groundhogg-calendar' );
						echo '(' . count( $appointments ) . ')'; ?>
					</div>
				</div>
				<div class="ic-section-content">
					<?php if ( ! empty( $appointments ) ): ?>
						<?php foreach ( $appointments as $appointment ):
							$appointment = new Appointment( $appointment->ID );
							//$appointments_table = new Appointments_Table();

							?>
							<div class="ic-section">
								<div class="ic-section-header">
									<div class="ic-section-header-content">
										<div class="basic-details">
											<a href="<?php echo esc_url( admin_page_url( 'gh_calendar', [
												'action'      => 'edit_appointment',
												'appointment' => $appointment->get_id()
											] ) ); ?> ">#<?php echo $appointment->get_id(); ?> </a>

											<?php $this->get_status( $appointment->get_status() ); ?>

										</div>
									</div>

								</div>
								<span
									class="subdata">March 19 2021<?php //echo ucfirst($appointment->column_stat_time());
									?> </span>
								<div class="ic-section-content">
									<span>111<?php echo $appointment->get_name(); ?></span>
									<span>222<?php //echo $appointment->column_stat_time();//date( get_date_time_format(),  );
										?></span>
									<span>333<?php // echo format_price($payment->get_total())
										?>  </span>

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
						<?php _e( 'Past Appointment', 'groundhogg-calendar' );
						echo '(' . count( $past_appointments ) . ')'; ?>
					</div>
				</div>
				<div class="ic-section-content">
					<?php if ( ! empty( $past_appointments ) ): ?>
						<?php foreach ( $past_appointments as $appointment ):
							$appointment = new Appointment( $appointment->ID );
							$table = new Appointments_Table( $appointment );

							?>
							<div class="ic-section">
								<div class="ic-section-header">
									<div class="ic-section-header-content">
										<div class="basic-details">
											<a href="<?php echo esc_url( admin_page_url( 'gh_calendar', [
												'action'      => 'edit_appointment',
												'appointment' => $appointment->get_id()
											] ) ); ?> ">#<?php echo $appointment->get_id(); ?> </a>

											<?php $this->get_status( $appointment->get_status() ); ?>

										</div>
									</div>

								</div>
								<span class="subdata"><?php echo ucfirst( $table->column_stat_time( $appointment ) );
									?> </span>
								<div class="ic-section-content">
									<span>111<?php echo $appointment->get_name(); ?></span>
									<span>222<?php //echo $appointment->column_stat_time();//date( get_date_time_format(),  );
										?></span>
									<span>333<?php // echo format_price($payment->get_total())
										?>  </span>

								</div>
							</div>
						<?php endforeach; ?>
					<?php
					else: _e( 'There is no any past appointment yet!!', 'groundhogg-calendar' ); endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
