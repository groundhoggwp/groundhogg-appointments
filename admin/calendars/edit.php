<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\admin_page_url;
use function Groundhogg\get_request_var;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
use function GroundhoggBookingCalendar\is_sms_plugin_active;

$tab_list = [
	'settings'     => __( 'Settings', 'groundhogg-calendar' ),
	'integration'  => __( 'Integration', 'groundhogg-calendar' ),
	'availability' => __( 'Availability', 'groundhogg-calendar' ),
	'notification' => __( 'Admin Notifications', 'groundhogg-calendar' ),
	'emails'       => __( 'Email Reminders', 'groundhogg-calendar' ),
	'sms'          => __( 'SMS Reminders', 'groundhogg-calendar' ),
	'embed'        => __( 'Embed', 'groundhogg-calendar' ),
	'view'         => __( 'Appointments', 'groundhogg-calendar' ),
	'delete'       => __( 'Delete', 'groundhogg-calendar' ),
	'new'       => __( 'NEw', 'groundhogg-calendar' ),
];

if ( ! is_sms_plugin_active() ) {
	unset( $tab_list['sms'] );
}

$tab = get_request_var( 'tab', 'settings' );

html()->tabs( $tab_list, $tab );

switch ( $tab ):
	case 'view':
		?>
		<script>window.location.replace("<?php echo admin_page_url( 'gh_appointments', [ 'selected' => get_url_var( 'calendar' ) ] ) ?>");</script>
		<?php
		break;
	case 'embed':
		include_once __DIR__ . '/embed.php';
		break;
	default:
	case 'settings':
		include_once __DIR__ . '/settings.php';
		break;
	case 'availability':
		include_once __DIR__ . '/availability.php';
		break;
	case 'integration':
		include_once __DIR__ . '/integration.php';
		break;
	case 'emails':
		include_once __DIR__ . '/emails.php';
		break;
	case 'notification':
		include_once __DIR__ . '/admin-notification.php';
		break;
	case 'sms' :
		if ( is_sms_plugin_active() ) {
			include_once __DIR__ . '/sms.php';
		}
		break;
	case 'delete':
		include __DIR__ . '/delete.php';
		break;
	case 'new':
		?>
    <div id="gh-calendar-settings"></div>
<?php
		break;
endswitch;
