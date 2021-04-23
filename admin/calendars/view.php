<?php
namespace GroundhoggBookingCalendar\Admin\Appointments;

use function Groundhogg\do_replacements;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use function Groundhogg\get_db;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$calendar_id = $_GET['calendar'];
$calendar    = new Calendar( $calendar_id );

$events = wp_json_encode( $calendar->get_events_for_full_calendar() );

$start_time = '00:00';
$end_time   = '23:59';

?>
<div id="col-container" class="wp-clearfix">
	<div id="col-left">
		<div class="col-wrap">
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
						'placeholder' => 'Appointment Name'
					] ); ?>

					<!--                                    <input name="name" id="appointmentname"type="text"  size="40" aria-required="true" placeholder="Appointment Name">-->
					<p class="description"><?php _e( 'Give nice name for your appointment.', 'groundhogg-calendar' ) ?></p>
				</div>
				<div class="form-field term-calendar-description-wrap">
					<label><?php _e( 'Note', 'groundhogg-calendar' ); ?></label>
					<?php echo html()->textarea( [
						'name'        => 'notes',
						'id'          => 'notes',
						'placeholder' => 'Any information that might be important.'
					] ); ?>
					<p class="description"><?php _e( 'Additional information about appointment.', 'groundhogg-calendar' ) ?></p>
				</div>
				<div class="form-field">
					<label><?php _e( 'Date', 'groundhogg-calendar' ); ?></label>
					<?php echo html()->date_picker( [
						'type'        => 'text',
						'id'          => 'date-picker',
						'placeholder' => 'Y-m-d'
					] );
					?>
				</div>
				<div class="time-slots form-field hidden">
					<label><?php _e( 'Time', 'groundhogg-calendar' ); ?></label>
					<div style="text-align: center;" id="spinner">
						<span class="spinner" style="float: none; visibility: visible"></span>
					</div>
					<div id="time-slots" class="select-time">
						<div id="select_time"></div>
					</div>
				</div>
				<div id="appointment-errors" class="appointment-errors hidden"></div>
				<div class="submit-wrap">
					<input type="button" name="btndisplay" id="btnalert" value="Book appointment"
					       class="button button-primary"/>
				</div>
			</div>
		</div>
	</div>
	<div id="col-right">
		<div class="col-wrap">
			<div class="postbox" style="margin-top: 10px;">
				<div class="inside">
					<div id='calendar' class=""></div>
					<table class="status-colors">
						<tr>
							<td class="scheduled"><b><?php _e( 'Scheduled', 'groundhogg-calendar' ); ?></b></td>
							<td class="canceled"><b><?php _e( 'Canceled', 'groundhogg-calendar' ); ?></b></td>
						</tr>
					</table>
					<input type="hidden" id="calendar_id" value="<?php echo $_GET['calendar']; ?>">
				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
  jQuery(function ($) {

    $('#external-events .fc-event').each(function () {
      // make the event draggable using jQuery UI
      $(this).draggable({
        zIndex: 999,
        revert: true,      // will cause the event to go back to its
        revertDuration: 0  //  original position after the drag
      })
    })

    var display = []
	  <?php
	  $availability = $calendar->get_meta( 'rules', true );
	  if (! empty( $availability )) :
	  foreach ( $availability as $avail ) :
	  ?>
    display.push({
      dow: [<?php echo $calendar->get_day_number( $avail['day'] ); ?>],
      start: '<?php echo $avail['start']; ?>',
      end: '<?php echo $avail['end']; ?>'
    })
	  <?php
	  endforeach;
	  else :
	  ?>
    display.push({
      dow: [1, 2, 3, 4, 5, 6, 0],
      start: '9:00',
      end: '17:00'
    })
	  <?php endif; ?>

    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,dayGridWeek,listWeek'
      },
      businessHours: display,
      initialView: 'timeGridWeek',
      events: <?php echo $events; ?>
    });
    calendar.render();
  })

</script>
