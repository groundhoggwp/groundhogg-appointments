<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\action_url;
use function Groundhogg\get_url_var;
use function Groundhogg\html;

?>
<h2><?php _e( 'Delete this calendar?', 'groundhogg-calendar' ) ?></h2>
<p><?php _e( 'Deleting the calendar will also delete any associated appointments including the events in any linked Google calendars and Zoom meetings.', 'groundhogg-calendar' ) ?></p>
<p><?php _e( 'This cannot be undone.', 'groundhogg-calendar' ) ?></p>
<p><?php echo html()->e( 'a', [
		'class' => 'button danger',
		'href'  => action_url( 'delete', [ 'calendar' => get_url_var( 'calendar' ) ] )
	], __( '⚠️ Delete this calendar', 'groundhogg-calendar' ) ); ?></p>