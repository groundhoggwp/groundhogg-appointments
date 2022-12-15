<?php

namespace GroundhoggBookingCalendar;

use GroundhoggBookingCalendar\Api\Calendar_Api;
use GroundhoggBookingCalendar\Classes\Appointment;
use function Groundhogg\admin_page_url;
use function Groundhogg\dequeue_theme_css_compat;
use function Groundhogg\dequeue_wc_css_compat;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
use function Groundhogg\is_option_enabled;
use function Groundhogg\managed_page_footer;
use function Groundhogg\managed_page_url;
use function Groundhogg\utils;

$calendar = get_query_var( 'calendar' );

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_scripts() {

	global $calendar, $appointment, $action;

	if ( ! $calendar || ! $calendar->exists() ) {
		return;
	}

	wp_enqueue_script( 'groundhogg-calendar' );

	dequeue_theme_css_compat();
	dequeue_wc_css_compat();

	do_action( 'enqueue_groundhogg_calendar_scripts' );
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_calendar_scripts' );

/**
 * Enqueue Calendar scripts
 */
function enqueue_calendar_styles() {
	wp_enqueue_style( 'groundhogg-admin-element' );
	wp_enqueue_style( 'groundhogg-calendar-frontend' );

	do_action( 'enqueue_groundhogg_calendar_styles' );
}

remove_action( 'wp_head', '_admin_bar_bump_cb' );

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_calendar_styles' );

status_header( 200 );
header( 'Content-Type: text/html; charset=utf-8' );
nocache_headers();

$is_framed = get_url_var( 'framed' );
$bg_color = get_url_var( 'bgcolor' );

$contact = get_contactdata();
$user = get_userdata( $calendar->get_user_id() );

if ( $contact ) {
	switch_to_locale( $contact->get_locale() );
}

$obj = [
	'locale'             => str_replace( '_', '-', get_locale() ),
	'routes'             => [
		'calendars'    => rest_url( Calendar_Api::NAME_SPACE . '/calendars' ),
		'appointments' => rest_url( Calendar_Api::NAME_SPACE . '/appointments' ),
	],
	'calendar'           => $calendar,
	'description'        => wpautop( $calendar->get_description() ),
	'appointment_length' => $calendar->get_appointment_length_formatted(),
	'timezones'          => utils()->location->get_time_zones(),
	'logo'               => has_custom_logo() ? wp_get_attachment_image_src( get_theme_mod( 'custom_logo' ), 'full' ) : false,
	'avatar'             => get_avatar( $user->ID, 80 ),
	'owner_name'         => $user->first_name . ' ' . $user->last_name,
	'contact'            => $contact ? [
		'name'  => $contact->get_full_name(),
		'email' => $contact->get_email(),
		'phone' => $contact->get_phone_number()
	] : [],
];

$appointment = get_query_var( 'appointment' );

if ( get_url_var( 'appt' ) ) {
	$appointment = new Appointment( get_url_var( 'appt' ), 'uuid' );
}

if ( $appointment ){
	$obj['appointment'] = [
		'start' => $appointment->get_start_time(),
		'uuid'  => $appointment->uuid
	];
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>

    <base target="_parent">
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <title><?php echo $calendar->get_name(); ?></title>
    <script>
      var GroundhoggCalendar = <?php echo wp_json_encode( $obj ) ?>
    </script>
    <script>

      let source = ''
      let formId = 0

      const postResizeData = () => {
        let body = document.body, html = document.documentElement
        let height = Math.max(body.scrollHeight, body.offsetHeight)
        let width = '100%'

        if (source) {
          source.postMessage({ height, width, id: formId }, '*')
        }
      }

      window.addEventListener('message', function (event) {

        if (typeof event.data.action !== 'undefined' && event.data.action === 'getFrameSize') {

          source = event.source
          formId = event.data.id

          postResizeData()
        }
      })

      window.addEventListener('load', () => {
        ['resize'].forEach(evt => {
          document.querySelector('#calendar').addEventListener(evt, () => {
            postResizeData()
          })
        })
      })
    </script>
	<?php wp_head(); ?>
	<?php if ( $is_framed ): ?>
        <style>
            html body.groundhogg-calendar-body {
                background-color: <?php echo $bg_color ?> !important;
            }
        </style>
	<?php endif; ?>
</head>
<body class="groundhogg-calendar-body groundhogg-admin-page <?php echo $is_framed ? 'framed' : '' ?>">
<div id="main">
    <div id="calendar" class="gh-panel">
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
		$links[] = html()->e( 'a', [
			'href' => admin_page_url( 'gh_calendar', [
				'calendar' => $calendar->get_id(),
				'action'   => 'edit'
			] )
		], __( 'Edit Calendar', 'groundhogg' ) );
	}

	if ( $appointment && current_user_can( 'edit_appointment', $appointment ) ) {
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
			'width' => 120,
			'src'   => GROUNDHOGG_ASSETS_URL . 'images/groundhogg-logo-black.svg'
		], null, true ) ) ); ?>
    </p>
<?php endif;
endif; ?>
</body>
</html>
