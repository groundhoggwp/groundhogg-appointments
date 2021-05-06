<?php

namespace GroundhoggBookingCalendar\Admin\Appointments;

use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Synced_Event;
use function Groundhogg\admin_page_url;
use function Groundhogg\convert_to_local_time;
use function Groundhogg\dashicon;
use function Groundhogg\dashicon_e;
use function Groundhogg\html;
use function Groundhogg\utils;
use function GroundhoggBookingCalendar\get_date_format;
use function GroundhoggBookingCalendar\get_time_format;

/**
 * @var $appointment Synced_Event
 */

$appointment->sync_all_details();

?>
<div class="appointment">
	<h2 class="appointment-title"><?php _e( $appointment->summary ); ?></h2>
	<p class="calendar-identity"><?php printf( __( 'Calendar: <b>%s</b>', 'groundhogg-calendar' ), html()->e( 'a', [
			'href' => $appointment->url
		], $appointment->calendar_name ) ); ?></p>
	<div class="appointment-details">
		<p><?php dashicon_e( 'calendar' ); ?> <b><?php _e( 'When' ) ?></b></p>
		<p><abbr
				title="<?php esc_attr_e( $appointment->start_time_pretty ); ?>"><?php printf( '%s, %s - %s',
					date_i18n( get_date_format(), convert_to_local_time( $appointment->start_time ) ),
					date_i18n( get_time_format(), convert_to_local_time( $appointment->start_time ) ),
					date_i18n( get_time_format(), convert_to_local_time( $appointment->end_time ) ) ) ?></abbr></p>
		<p><?php dashicon_e( 'location-alt' ); ?> <b><?php _e( 'Location' ) ?></b></p>
		<p><?php echo make_clickable( $appointment->location ); ?></p>
		<p><?php dashicon_e( 'text' ); ?> <b><?php _e( 'Details' ) ?></b></p>
		<div class="details">
			<?php echo make_clickable( wpautop( $appointment->description ) ); ?>
		</div>
	</div>
</div>