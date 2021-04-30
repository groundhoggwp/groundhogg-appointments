<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Admin\Appointments\Appointments_Table;
use function GroundhoggBookingCalendar\is_sms_plugin_active;

$tab_list = [
	'view'         => __( 'View', 'groundhogg-calendar' ),
	'embed'        => __( 'Embed', 'groundhogg-calendar' ),
	'settings'     => __( 'Settings', 'groundhogg-calendar' ),
	'integration'  => __( 'Integration', 'groundhogg-calendar' ),
	'availability' => __( 'Availability', 'groundhogg-calendar' ),
	'notification' => __( 'Admin Notifications', 'groundhogg-calendar' ),
	'emails'       => __( 'Email Reminders', 'groundhogg-calendar' ),
	'sms'          => __( 'SMS Reminders', 'groundhogg-calendar' ),
	'list'         => __( 'Appointments', 'groundhogg-calendar' ),
];

if ( ! is_sms_plugin_active() ) {
	unset( $tab_list['sms'] );
}

html()->tabs( $tab_list );

$tab = get_request_var( 'tab', 'view' );
switch ( $tab ):
	default:
	case 'view':
		include_once dirname( __FILE__ ) . '/view.php';
		break;
	case 'embed':
		include_once dirname( __FILE__ ) . '/embed.php';
		break;
	case 'settings':
		include_once dirname( __FILE__ ) . '/settings.php';
		break;
	case 'availability':
		include_once dirname( __FILE__ ) . '/availability.php';
		break;
	case 'integration':
		include_once dirname( __FILE__ ) . '/integration.php';
		break;
	case 'emails':
		include_once dirname( __FILE__ ) . '/emails.php';
		break;
	case 'notification':
		include_once dirname( __FILE__ ) . '/admin-notification.php';
		break;
	case 'sms' :
		if ( is_sms_plugin_active() ) {
			include_once dirname( __FILE__ ) . '/sms.php';
		}
		break;
	case 'list':

		if ( ! class_exists( 'Appointments_Table' ) ) {
			include_once dirname( __FILE__ ) . '/../appointments/table.php';
		}

		$appointments_table = new Appointments_Table();
		$appointments_table->prepare_items();
		$appointments_table->display();

		break;
endswitch;