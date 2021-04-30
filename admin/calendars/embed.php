<?php

namespace GroundhoggBookingCalendar\Admin;

use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
use function Groundhogg\managed_page_url;

$calendar = new Calendar( get_url_var( 'calendar' ) );

html()->start_form_table();

html()->start_row();

html()->th( __( 'Shortcode' ) );
html()->td( html()->input( array(
	'type'     => 'text',
	'name'     => '',
	'id'       => '',
	'style'    => [ 'max-width' => '100%' ],
	'class'    => 'regular-text code',
	'value'    => sprintf( '[gh_calendar id="%d" name="%s"]', $calendar->get_id(), $calendar->get_name() ),
	'readonly' => true,
	'onfocus'  => 'this.select()'
) ) );

html()->end_row();

html()->th( __( 'Direct URL', 'groundhogg-calendar' ) );
html()->td( html()->input( array(
	'type'     => 'text',
	'name'     => '',
	'id'       => '',
	'style'    => [ 'max-width' => '100%' ],
	'class'    => 'regular-text code',
	'value'    => managed_page_url( sprintf( 'calendar/%s/', $calendar->slug ) ),
	'readonly' => true,
	'onfocus'  => 'this.select()'
) ) );

html()->end_row();
html()->end_form_table();