<?php

namespace GroundhoggBookingCalendar\Admin\Appointments;

use function Groundhogg\array_map_keys;
use function Groundhogg\array_map_with_keys;
use function Groundhogg\get_db;
use function Groundhogg\get_url_var;
use function Groundhogg\html;


?>
<div class="wp-clearfix"></div>
<div id="quick-search" class="postbox">
	<form method="get">
		<?php html()->hidden_inputs( [
			'page' => get_url_var( 'page' )
		] ); ?>
		<div class="filters">
			<?php

			$calendars = get_db( 'calendars' )->query( [
				'user_id' => current_user_can( 'view_own_calendar' ) ? get_current_user_id() : false,
			] );

			echo html()->select2( [
				'options'  => array_map_with_keys( array_map_keys( $calendars, function ( $i, $cal ) {
					return $cal->ID;
				} ), function ( $cal ) {
					return $cal->name;
				} ),
				'selected' => get_url_var( 'calendar_id' ),
				'multiple' => true,
				'name'     => 'calendar_id',
				'placeholder' => __( 'Filter by your calendars', 'groundhogg-calendar' )
			] );

			echo html()->checkbox( [
				'label'   => __( 'Hide synced events', 'groundhogg-calendar' ),
				'type'    => 'checkbox',
				'name'    => 'hide_synced_events',
				'id'      => 'hide_synced_events',
				'class'   => '',
				'value'   => '1',
				'checked' => get_url_var( 'hide_synced_events' ),
				'title'   => '',
			] );

			echo html()->submit( [
				'text' => __( 'Search', 'groundhogg-calendar' )
			] ) ?>
		</div>
	</form>
</div>
