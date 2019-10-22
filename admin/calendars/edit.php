<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Admin\Appointments\Appointments_Table;
use function GroundhoggBookingCalendar\is_sms_plugin_active;

$tab_list = [
    'view' => __( 'View', 'groundhogg-calendar' ), // Show calendar and add appointments
    'settings' => __( 'Settings', 'groundhogg-calendar' ), // Show calendar settings
    'availability' => __( 'Availability', 'groundhogg-calendar' ), // Show calendar settings
    'emails' => __( 'Emails', 'groundhogg-calendar' ), // Show calendar reminders
    'list' => __( 'List', 'groundhogg-calendar' ), // show appointments in list table.
];
if ( is_sms_plugin_active() ) {
    $tab_list [ 'sms' ] = __( 'SMS', 'groundhogg-calendar' );
}
html()->tabs( $tab_list );

$tab = get_request_var( 'tab', 'view' );
switch ( $tab ):
    default:
    case 'view':
        include_once dirname( __FILE__ ) . '/view.php';
        break;
    case 'settings':
        include_once dirname( __FILE__ ) . '/settings.php';
        break;
    case 'availability':
        include_once dirname( __FILE__ ) . '/availability.php';
        break;
    case 'emails':
        include_once dirname( __FILE__ ) . '/emails.php';
        break;
    case 'sms' :
        if ( is_sms_plugin_active() ) {
            include_once dirname( __FILE__ ) . '/sms.php';
        }
        break;
    case 'list':

        if ( !class_exists( 'Appointments_Table' ) ) {
            include_once dirname( __FILE__ ) . '/../appointments/table.php';
        }

        $appointments_table = new Appointments_Table();
        $appointments_table->prepare_items();
        $appointments_table->display();

        break;
endswitch;