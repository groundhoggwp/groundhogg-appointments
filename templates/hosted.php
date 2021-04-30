<?php

namespace GroundhoggBookingCalendar;

use function Groundhogg\managed_page_footer;
use function Groundhogg\managed_page_head;
use GroundhoggBookingCalendar\Classes\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include GROUNDHOGG_PATH . 'templates/managed-page.php';

add_action( 'wp_head', function () {
	?>
	<style>
        #main {
            max-width: 850px;
        }
	</style>
	<?php
} );

$calendar_id = get_query_var( 'calendar_id' );

$calendar = new Calendar( $calendar_id );

managed_page_head( $calendar->get_name(), 'view' );

echo do_shortcode( sprintf( '[gh_calendar id="%d"]', $calendar->get_id() ) );

managed_page_footer();