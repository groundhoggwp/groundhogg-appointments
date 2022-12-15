<?php

namespace GroundhoggBookingCalendar;


use Groundhogg\Email;
use Groundhogg\Event;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\Google_Connection;
use GroundhoggBookingCalendar\Classes\Synced_Event;
use GroundhoggBookingCalendar\Connections\Zoom;
use GroundhoggSMS\Classes\SMS;
use GroundhoggSMS\SMS_Services;
use function Groundhogg\array_map_keys;
use function Groundhogg\array_map_to_class;
use function Groundhogg\do_replacements;
use function Groundhogg\email_kses;
use function Groundhogg\emergency_init_dbs;
use function Groundhogg\get_array_var;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Appointment_Reminder;
use function Groundhogg\get_db;
use function Groundhogg\get_default_from_email;
use function Groundhogg\get_default_from_name;
use function Groundhogg\get_object_ids;
use function Groundhogg\get_request_query;
use function Groundhogg\get_url_var;
use function Groundhogg\isset_not_empty;

/**
 * Zoom stuff!
 *
 * @return Zoom
 */
function zoom() {
	static $zoom;

	if ( ! $zoom ) {
		$zoom = new Zoom();
	}

	return $zoom;
}

function install_tables() {

	emergency_init_dbs();

	$new_tables = [
		'synced_events',
		'google_connections',
		'google_calendars',
		'appointments',
		'calendars',
		'calendarmeta',
		'appointmentmeta',
	];

	foreach ( $new_tables as $table ) {
		get_db( $table )->create_table();
	}
}

/**
 * Either set or get the current appointment
 *
 * @param Appointment $appointment
 *
 * @return false|Appointment|mixed
 */
function current_appointment( $appointment = false ) {
	static $_appointment;

	if ( is_a( $appointment, Appointment::class ) ) {
		$_appointment = $appointment;
	}

	if ( $appointment === null ) {
		$_appointment = false;
	}

	return $_appointment;
}

/**
 * @param $value mixed
 * @param $a     mixed Lower
 * @param $b     mixed Higher
 *
 * @return bool
 */
function in_between( $value, $a, $b ) {
	return ( $value > $a && $value < $b );
}

/**
 * @param $value mixed
 * @param $a     mixed Lower
 * @param $b     mixed Higher
 *
 * @return bool
 */
function in_between_inclusive( $value, $a, $b ) {
	return ( $value >= $a && $value <= $b );
}

/**
 * Get the days of the week as an array, or if you pass a day get the display name of that day
 *
 * @param string $day
 *
 * @return array|mixed
 */
function days_of_week( $day = '' ) {
	$days = [
		'monday'    => __( 'Monday' ),
		'tuesday'   => __( 'Tuesday' ),
		'wednesday' => __( 'Wednesday' ),
		'thursday'  => __( 'Thursday' ),
		'friday'    => __( 'Friday' ),
		'saturday'  => __( 'Saturday' ),
		'sunday'    => __( 'Sunday' ),
	];

	if ( empty( $day ) ) {
		return $days;
	}

	return get_array_var( $days, $day, 'monday' );
}


/**
 * Setup the step for an event as the Reminder notification type
 *
 * @param $event Event
 */
function setup_reminder_notification_object( $event ) {
    switch ( $event->get_event_type() ){
        case Appointment_Reminder::NOTIFICATION_TYPE;
	        $event->step = new Appointment_Reminder( $event );
	        break;
    }
}

add_action( 'groundhogg/event/post_setup', __NAMESPACE__ . '\setup_reminder_notification_object' );

/**
 * Whether the sms plugin is active
 *
 * @return bool
 */
function is_sms_plugin_active() {
	return \Groundhogg\is_sms_plugin_active();
}

/**
 * List of all calendars
 *
 * @return array
 */
function get_calendar_list() {

	$calendars = Plugin::$instance->dbs->get_db( 'calendars' )->query();

	$calendar_list = array();
	$default       = 0;
	foreach ( $calendars as $calendar ) {
		if ( ! $default ) {
			$default = $calendar->ID;
		}
		$calendar_list[ $calendar->ID ] = $calendar->name;
	}

	return $calendar_list;
}

/**
 * Replace anything not in alpha numeric with a blank.
 *
 * @return array|string|string[]|null
 */
function generate_google_uuid() {
	return sanitize_google_uuid( wp_generate_uuid4() );
}

/**
 * Remove "-" from UUID
 *
 * @param $uuid
 *
 * @return array|string|string[]|null
 */
function sanitize_google_uuid( $uuid ) {
	return preg_replace( "/[^A-z0-9]/", "", $uuid );
}

/**
 * Convert a duration to human-readable format.
 *
 * @since 5.1.0
 *
 * @param string $duration Duration will be in string format (HH:ii:ss) OR (ii:ss),
 *                         with a possible prepended negative sign (-).
 *
 * @return string|false A human-readable duration string, false on failure.
 */
function better_human_readable_duration( $duration = '' ) {
	if ( ( empty( $duration ) || ! is_string( $duration ) ) ) {
		return false;
	}

	$duration = trim( $duration );

	// Remove prepended negative sign.
	if ( '-' === substr( $duration, 0, 1 ) ) {
		$duration = substr( $duration, 1 );
	}

	// Extract duration parts.
	$duration_parts = array_reverse( explode( ':', $duration ) );
	$duration_count = count( $duration_parts );

	$hour   = null;
	$minute = null;
	$second = null;

	if ( 3 === $duration_count ) {
		// Validate HH:ii:ss duration format.
		if ( ! ( (bool) preg_match( '/^([0-9]+):([0-5]?[0-9]):([0-5]?[0-9])$/', $duration ) ) ) {
			return false;
		}
		// Three parts: hours, minutes & seconds.
		list( $second, $minute, $hour ) = $duration_parts;
	} else if ( 2 === $duration_count ) {
		// Validate ii:ss duration format.
		if ( ! ( (bool) preg_match( '/^([0-5]?[0-9]):([0-5]?[0-9])$/', $duration ) ) ) {
			return false;
		}
		// Two parts: minutes & seconds.
		list( $second, $minute ) = $duration_parts;
	} else {
		return false;
	}

	$human_readable_duration = array();

	// Add the hour part to the string.
	if ( is_numeric( $hour ) && $hour > 0 ) {
		/* translators: Time duration in hour or hours. */
		$human_readable_duration[] = sprintf( _n( '%s hour', '%s hours', $hour ), (int) $hour );
	}

	// Add the minute part to the string.
	if ( is_numeric( $minute ) && $minute > 0 ) {
		/* translators: Time duration in minute or minutes. */
		$human_readable_duration[] = sprintf( _n( '%s minute', '%s minutes', $minute ), (int) $minute );
	}

	// Add the second part to the string.
	if ( is_numeric( $second ) && $second > 0 ) {
		/* translators: Time duration in second or seconds. */
		$human_readable_duration[] = sprintf( _n( '%s second', '%s seconds', $second ), (int) $second );
	}

	return implode( ', ', $human_readable_duration );
}

/**
 * Wrapper function to get the date format.
 *
 * @return mixed|void
 */
function get_date_format() {
	return get_option( 'date_format' );
}

/**
 * Wrapper function to get the time format.
 *
 * @return mixed|void
 */
function get_time_format() {
	return get_option( 'time_format' );
}

/**
 * Get the unix stamp to show in the given timezone
 *
 * @param $time
 * @param $time_zone
 *
 * @return int
 */
function get_in_time_zone( $time, $time_zone ) {
	try {
		return Plugin::$instance->utils->date_time->convert_to_foreign_time( $time, $time_zone );
	} catch ( \Exception $exception ) {
		return $time;
	}
}

/**
 * Get the tz db name
 *
 * @return string
 */
function get_tz_db_name() {

	if ( get_option( 'timezone_string' ) ) {
		return get_option( 'timezone_string' );
	}

	$offset = Plugin::$instance->utils->date_time->get_wp_offset( true );

	$tz = timezone_name_from_abbr( '', $offset, 1 );
	// Workaround for bug #44780
	if ( $tz === false ) {
		$tz = timezone_name_from_abbr( '', $offset, 0 );
	}

	return $tz;
}

/**
 * Gets the maximum booking period of all the calendars
 *
 * @throws \Exception
 * @return int|mixed
 */
function get_max_booking_period() {

	$all_calendars = get_db( 'calendars' )->query();
	$period        = 0;

	foreach ( $all_calendars as $calendar ) {
		$calendar = new Calendar( $calendar );
		$period   = max( $period, $calendar->get_max_booking_period( false )->getTimestamp() );
	}

	return max( $period, MONTH_IN_SECONDS );
}

/**
 * Create array of typical 9:00 AM to 5:00 PM Monday - Friday working hours
 *
 * @return array
 */
function get_default_availability() {
	$rules = [];

	$times = [ 'start' => '09:00', 'end' => '17:00' ];

	$days = days_of_week();

	// Remove Sat & Sun
	$days = array_slice( $days, 0, 5 );

	$days = array_keys( $days );

	foreach ( $days as $day ) {
		$rules[] = array_merge( [ 'day' => $day ], $times );
	}

	return $rules;
}

/**
 * Set the default settings for the calendar
 *
 * @param $calendar Calendar
 * @param $hours    int
 * @param $minutes  int
 */
function set_calendar_default_settings( $calendar, $hours = 1, $minutes = 0 ) {

	$calendar->generate_slug();

	// max booking period in availability
	$calendar->update_meta( 'max_booking_period_count', 1 );
	$calendar->update_meta( 'max_booking_period_type', 'months' );

	//min booking period in availability
	$calendar->update_meta( 'min_booking_period_count', 4 );
	$calendar->update_meta( 'min_booking_period_type', 'hours' );

	//set default settings
	$calendar->update_meta( 'slot_hour', $hours );
	$calendar->update_meta( 'slot_minute', $minutes );

	$calendar->update_meta( 'rules', get_default_availability() );

	// Create default emails...
	include __DIR__ . '/../templates/emails.php';

	/** @var array $emails */

	foreach ( $emails as &$email ) {
		$email = new Email( [
			'title'     => sprintf( '%s (%s)', $email['title'], $calendar->get_name() ),
			'subject'   => $email['subject'],
			'content'   => $email['content'],
			'status'    => 'ready',
			'from_user' => $calendar->get_user_id(),
			'author'    => $calendar->get_user_id(),
		] );

		$email->update_meta( 'message_type', \Groundhogg_Email_Services::TRANSACTIONAL );
	}

	// Configure the email notifications
	$calendar->update_meta( 'email_notifications', [
//		Appointment_Reminder::SCHEDULED   => $emails['scheduled']->get_id(),
//		Appointment_Reminder::RESCHEDULED => $emails['rescheduled']->get_id(),
//		Appointment_Reminder::CANCELLED   => $emails['cancelled']->get_id(),
	] );

	// set one hour before reminder by default
	$calendar->update_meta( 'email_reminders', [
		[
			'when'     => 'before',
			'period'   => 'hours',
			'number'   => 1,
			'email_id' => $emails['reminder']->get_id()
		]
	] );

	// Create default SMS
	if ( is_sms_plugin_active() ) {

		// Create default emails...
		include __DIR__ . '/../templates/sms.php';

		/** @var array $sms */

		foreach ( $sms as &$s ) {
			$s = new SMS( [
				'title'   => sprintf( '%s (%s)', $s['title'], $calendar->get_name() ),
				'message' => $s['content'],
				'type'    => SMS_Services::TRANSACTIONAL
			] );
		}

		$calendar->update_meta( 'sms_notifications', [
//			SMS_Reminder::SCHEDULED   => $sms['scheduled']->get_id(),
//			SMS_Reminder::RESCHEDULED => $sms['rescheduled']->get_id(),
//			SMS_Reminder::CANCELLED   => $sms['cancelled']->get_id(),
		] );

		// set one hour before reminder by default

		$calendar->update_meta( 'sms_reminders', [
			[
				'when'   => 'before',
				'period' => 'hours',
				'number' => 1,
				'sms_id' => $sms['reminder']->get_id()
			]
		] );
	}

	$admin_notifications = [
		'sms'                             => is_sms_plugin_active(),
//		Appointment_Reminder::SCHEDULED   => true,
//		Appointment_Reminder::RESCHEDULED => true,
//		Appointment_Reminder::CANCELLED   => true,
	];

	// Simplify code
	$calendar->update_meta( 'enabled_admin_notifications', $admin_notifications );
}

/**
 * Gets all events for the full calendar list in the appointments page
 *
 * @return array
 */
function get_all_events_for_full_calendar() {

	$local_query  = get_request_query();
	$synced_query = [];

	// Only show appointments the calendar owner can see.
	if ( current_user_can( 'view_own_calendar' ) ) {

		$owned_calendars = get_db( 'calendars' )->query( [
			'user_id' => get_current_user_id()
		] );

		array_map_to_class( $owned_calendars, Calendar::class );

		// Filter by including appointments whose parent calendar is owned by the current user
		if ( isset_not_empty( $local_query, 'calendar_id' ) ) {
			$local_query['calendar_id'] = array_intersect( wp_parse_id_list( $local_query['calendar_id'] ), get_object_ids( $owned_calendars ) );
		} else {
			$local_query['calendar_id'] = get_object_ids( $owned_calendars );
		}

		// Can only see synced events from calendars linked to their own calendar
		$connected_calendars = array_reduce( $owned_calendars, function ( $carry, $calendar ) {
			return array_unique( array_merge( $carry, $calendar->get_google_calendar_list() ) );
		}, [] );

		$synced_query['local_gcalendar_id'] = $connected_calendars;
	}

	$local_query  = array_filter( $local_query );
	$synced_query = array_filter( $synced_query );

	$local_appointments = get_db( 'appointments' )->query( $local_query );

	$local_events = array_map_keys( array_map( function ( $event ) {
		$event = new Appointment( $event );

		return $event->get_for_full_calendar();
	}, $local_appointments ), function ( $i, $event ) {
		return $event['id'];
	} );

	$google_events = [];

	if ( ! get_url_var( 'hide_synced_events' ) ) {
		$google_events = get_db( 'synced_events' )->query( $synced_query );
		$google_events = array_map_keys( array_map( function ( $event ) {
			$event = new Synced_Event( $event );

			return $event->get_for_full_calendar();
		}, $google_events ), function ( $i, $event ) {
			return $event['id'];
		} );
	}

	return array_values( array_merge( $google_events, $local_events ) );
}

/**
 * Generate a slug based on the calendar name
 *
 * return string
 */
function validate_calendar_slug( $slug, $id = false ) {

	$slug = sanitize_title( $slug );

	while ( $existing = get_db( 'calendars' )->get_by( 'slug', $slug ) ) {

		// The existing slug is the one from the current calendar
		if ( $id === absint( $existing->ID ) ) {
			return $slug;
		}

		$slug_num = preg_match( '/-(\d+)$/', $slug, $matches ) ? absint( $matches[1] ) : 1;
		$slug     = empty( $matches ) ? $slug . '-' . $slug_num : preg_replace( '/-(\d+)$/', '-' . ( $slug_num + 1 ), $slug );
	}

	return $slug;
}

/**
 * Get the account ID of a users connected google account
 *
 * @param $user_id
 *
 * @return mixed
 */
function get_user_google_account_id( $user_id = false ) {

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	return get_user_meta( $user_id, 'gh_google_account_id', true );
}

/**
 * Get the Google Connection of a user
 *
 * @param $user_id
 *
 * @return false|Google_Connection
 */
function get_user_google_connection( $user_id = false ) {

	$account_id = get_user_google_account_id( $user_id );

	if ( ! $account_id ) {
		return false;
	}

	$connection = new Google_Connection( $account_id, 'account_id' );

	if ( ! $connection->exists() ) {
		return false;
	}

	return $connection;
}

/**
 * Get a user's zoom account id
 *
 * @param $user_id
 *
 * @return mixed
 */
function get_user_zoom_account_id( $user_id = false ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	return get_user_meta( $user_id, 'gh_zoom_account_id', true );
}

/**
 * Send and email notification to the contact
 *
 * @param $appointment Appointment
 * @param $template string|array|int
 *
 * @return bool
 */
function send_contact_email_notification( $appointment, $template ) {

    current_appointment( $appointment );

    if ( ! is_array( $template ) ){
        $template = $appointment->get_calendar()->get_contact_email_notification_template( $template );
    }

	// No content or subject defined
	if ( empty( $template['content'] ) || empty( $template['subject'] ) ){
		return false;
	}

	// set for replacements
	$content  = do_replacements( $template['content'], $appointment->get_contact() );
	$content  .= wpautop( get_option( 'gh_custom_email_footer_text' ) );
	$content  = email_kses( $content );

	$subject = sanitize_text_field( do_replacements( $template['subject'], $appointment->get_contact() ) );

	$headers = [
		'Content-Type: text/html',
		sprintf( 'From: %s <%s>', get_default_from_name(), get_default_from_email() ),
		// Reply to the contact
		sprintf( 'Reply-To: %s', $appointment->get_owner()->user_email )
	];

	$recipients = [ $appointment->get_contact()->get_email() ];

	/**
	 * Filter the admin notification recipients
	 *
	 * @param array       $recipients
	 * @param Appointment $appointment
	 * @param string      $status
	 */
	$recipients = apply_filters( 'groundhogg/calendar/send_contact_email_notification/recipients', $recipients, $appointment );

	return \Groundhogg_Email_Services::send_transactional( $recipients, $subject, $content, $headers );
}

/**
 * Send an email notification to the admin for appointment details
 *
 * @param $appointment Appointment
 * @param $template string|array|int
 *
 * @return bool
 */
function send_admin_email_notification( $appointment, $template ) {

	current_appointment( $appointment );

	if ( ! is_array( $template ) ){
		$template = $appointment->get_calendar()->get_admin_email_notification_template( $template );
	}

	// No content or subject defined
	if ( empty( $template['content'] ) || empty( $template['subject'] ) ){
		return false;
	}

	$content  = do_replacements( $template['content'], $appointment->get_contact() );
	$content  = email_kses( $content );
	$subject  = sanitize_text_field( do_replacements( $template['subject'], $appointment->get_contact() ) );

	$headers = [
		'Content-Type: text/html',
		sprintf( 'From: %s <%s>', get_default_from_name(), get_default_from_email() ),
		// Reply to the contact
		sprintf( 'Reply-To: %s', $appointment->get_contact()->get_email() )
	];

	$recipients = [ $appointment->get_owner()->user_email ];

	/**
	 * Filter the admin notification recipients
	 *
	 * @param array       $recipients
	 * @param Appointment $appointment
	 * @param string      $status
	 */
	$recipients = apply_filters( 'groundhogg/calendar/send_admin_email_notification/recipients', $recipients, $appointment );

	return \Groundhogg_Email_Services::send_transactional( $recipients, $subject, $content, $headers );
}
