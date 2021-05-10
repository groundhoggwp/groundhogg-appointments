<?php

namespace GroundhoggBookingCalendar;

use function Groundhogg\admin_page_url;use function Groundhogg\dequeue_theme_css_compat;
use function Groundhogg\dequeue_wc_css_compat;
use function Groundhogg\get_url_var;use function Groundhogg\html;use function Groundhogg\is_option_enabled;use function Groundhogg\managed_page_footer;use function Groundhogg\managed_page_url;

$calendar = get_query_var( 'calendar' );
$appointment = get_query_var( 'appointment' );
$action = get_query_var( 'action' );

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_scripts() {

	global $calendar, $appointment, $action;

	if ( ! $calendar || ! $calendar->exists() ) {
		return;
	}

	wp_enqueue_script( 'groundhogg-appointments-frontend' );

	dequeue_theme_css_compat();
	dequeue_wc_css_compat();

	$localize = [
		'calendar_id'    => $calendar->get_id(),
		'item'           => $calendar,
		'start_of_week'  => get_option( 'start_of_week' ),
		'min_date'       => $calendar->get_min_booking_period( true ),
		'max_date'       => $calendar->get_max_booking_period( true ),
		'disabled_days'  => $calendar->get_dates_no_slots(),
		'day_names'      => [
			__( 'SUN', 'groundhogg-calendar' ),
			__( 'MON', 'groundhogg-calendar' ),
			__( 'TUE', 'groundhogg-calendar' ),
			__( 'WED', 'groundhogg-calendar' ),
			__( 'THU', 'groundhogg-calendar' ),
			__( 'FRI', 'groundhogg-calendar' ),
			__( 'SAT', 'groundhogg-calendar' )
		],
		'ajaxurl'        => admin_url( 'admin-ajax.php' ),
		'invalidDateMsg' => __( 'Please select a valid time slot.', 'groundhogg-calendar' )
	];

	if ( $appointment && $appointment->exists() ) {
		$localize['reschedule']  = $appointment->get_id();
		$localize['appointment'] = $appointment;
		$localize['appt_action'] = $action;
	}

	wp_localize_script( 'groundhogg-appointments-frontend', 'BookingCalendar', $localize );

	do_action( 'enqueue_groundhogg_calendar_scripts' );
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_calendar_scripts' );

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_styles() {
	wp_enqueue_style( 'gh-jquery-ui-datepicker' );
	wp_enqueue_style( 'groundhogg-calendar-frontend' );

	do_action( 'enqueue_groundhogg_calendar_styles' );
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_calendar_styles' );

status_header( 200 );
header( 'Content-Type: text/html; charset=utf-8' );
nocache_headers();

$is_framed = get_url_var( 'framed' );
$bg_color = get_url_var( 'bgcolor' );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<base target="_parent">
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<title><?php echo $calendar->get_name(); ?></title>
	<?php wp_head(); ?>
	<?php if ( $is_framed ): ?>
		<style>
            html body.groundhogg-calendar-body {
                background-color: <?php echo $bg_color ?> !important;
            }
		</style>
	<?php endif; ?>
</head>
<body class="groundhogg-calendar-body <?php echo $is_framed ? 'framed' : '' ?>">
<div id="main">
	<div class="loader-overlay" style="display: none"></div>
	<div class="loader-wrap" style="display: none">
		<p><span class="gh-loader"></span></p>
	</div>
	<div id="calendar-view">
		<div class="groundhogg-calendar">
			<div id="calendar-details" class="view">
				<?php do_action( 'groundhogg/calendar/template/details', $calendar ); ?>
			</div>
			<?php if ( $action !== 'cancel' && ( ! $appointment || ! $appointment->is_cancelled() ) ): ?>
				<div id="calendar-date-picker" class="view">
					<?php do_action( 'groundhogg/calendar/template/date_picker', $calendar ); ?>
				</div>
				<div id="calendar-form" class="view">
					<?php do_action( 'groundhogg/calendar/template/form', $calendar ); ?>
				</div>
			<?php else: ?>
				<div id="calendar-cancel" class="view">
					<?php do_action( 'groundhogg/calendar/template/cancel', $appointment ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php if ( ! $is_framed ) :

	$privacy_policy_url = function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url() ? get_privacy_policy_url() : get_option( 'gh_privacy_policy' );

	$links = [
		html()->e( 'a', [ 'href' => home_url( '/' ) ], sprintf( _x( '&larr; Back to %s', 'site' ), get_bloginfo( 'title', 'display' ) ) ),
		html()->e( 'a', [ 'href' => managed_page_url( 'preferences/profile/' ) ], __( 'Edit Profile', 'groundhogg' ) ),
		html()->e( 'a', [ 'href' => $privacy_policy_url ], __( 'Privacy Policy', 'groundhogg' ) ),
	];

	if ( current_user_can( 'edit_calendar' ) ) {
		$links[] = html()->e( 'a', [ 'href' => admin_page_url( 'gh_calendar', [ 'calendar' => $calendar->get_id(), 'action' => 'edit' ] ) ], __( 'Edit Calendar', 'groundhogg' ) );
	}

	if ( current_user_can( 'edit_appointment' ) && $appointment ) {
		$links[] = html()->e( 'a', [
			'href' => admin_page_url( 'gh_appointments', [
				'appointment' => $appointment->get_id()
			] )
		], __( 'Edit Appointment', 'groundhogg' ) );
	}


	$html = implode( ' | ', $links );

	?>
	<p id="extralinks"><?php echo $html; ?></p>
	<?php if ( is_option_enabled( 'gh_affiliate_link_in_email' ) ): ?>
	<p id="credit">
		<?php printf( __( "Powered by %s", 'groundhogg' ), html()->e( 'a', [
			'target' => '_blank',
			'href'   => add_query_arg( [
				'utm_source'   => 'email',
				'utm_medium'   => 'footer-link',
				'utm_campaign' => 'email-affiliate',
				'aff'          => absint( get_option( 'gh_affiliate_id' ) ),
			], 'https://www.groundhogg.io/pricing/' )
		], html()->e( 'img', [
			'width' => 100,
			'src'   => GROUNDHOGG_ASSETS_URL . 'images/groundhogg-logo-black.svg'
		], null, true ) ) ); ?>
	</p>
<?php endif;
endif; ?>
</body>
</html>