<?php
namespace GroundhoggBookingCalendar\Admin\Appointments;

use function Groundhogg\do_replacements;
use function Groundhogg\get_request_var;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
use function Groundhogg\get_db;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$calendar = new Calendar( get_url_var( 'calendar' ) );

?>
<div id="col-container" class="wp-clearfix">
	<div id="col-left">
		<div class="col-wrap">
			<form id="booking-form">
				<div class="form-wrap">
					<div class="form-field term-contact-wrap">
						<label><?php _e( 'Select Contact' ) ?></label>
						<?php
						$contact_details = [
							'name' => 'contact_id',
							'id'   => 'contact-id',
						];
						$contact_id      = absint( get_request_var( 'contact' ) );
						if ( $contact_id ) {
							$contact_details ['selected'] = [ $contact_id ];
							$contact_details ['disabled'] = true;
							echo "<input type='hidden' name='redirect' value='true' />";
						}
						echo html()->dropdown_contacts( $contact_details ); ?>
						<p class="description"><?php _e( 'Please select client contact from contact list.', 'groundhogg-calendar' ) ?></p>
					</div>
					<div class="form-field term-calendar-name-wrap">
						<label><?php _e( 'Appointment Name' ) ?></label>
						<?php echo html()->input( [
							'name'        => 'name',
							'id'          => 'appointmentname',
							'type'        => 'text',
							'placeholder' => 'Appointment Name',
							'value'       => $calendar->get_meta( 'default_name' ),
						] ); ?>

						<!--                                    <input name="name" id="appointmentname"type="text"  size="40" aria-required="true" placeholder="Appointment Name">-->
						<p class="description"><?php _e( 'Give nice name for your appointment.', 'groundhogg-calendar' ) ?></p>
					</div>
					<div class="form-field term-calendar-description-wrap">
						<label><?php _e( 'Any additional information?', 'groundhogg-calendar' ); ?></label>
						<?php echo html()->textarea( [
							'name'        => 'additional',
							'id'          => 'additional',
							'rows'        => 3,
							'placeholder' => __( 'Any information that might be important.', 'groundhogg-calendar' ),
//							'value'       => $calendar->get_meta( 'default_note' ),
						] ); ?>
						<p class="description"><?php _e( 'Additional information about appointment that might be useful for you or the guest.', 'groundhogg-calendar' ) ?></p>
					</div>
					<div class="form-field">
						<div style="display: flex">
							<div id="date-picker"></div>
							<div class="time-slots">
								<div style="text-align: center;" id="spinner">
									<span class="spinner" style="float: none; visibility: visible"></span>
								</div>
								<div id="time-slots" class="select-time">
									<div id="select_time"></div>
								</div>
							</div>
						</div>
					</div>
					<div id="appointment-errors" class="appointment-errors hidden"></div>
					<div class="submit-wrap">
						<input type="button" name="btndisplay" id="btnalert" value="Book appointment"
						       class="button button-primary"/>
					</div>
				</div>
			</form>
		</div>
	</div>
	<div id="col-right">
		<div class="col-wrap">
			<div class="postbox" style="margin-top: 10px;">
				<div class="inside">
					<div id='calendar' class=""></div>
				</div>
			</div>
		</div>
	</div>
</div>