<?php

namespace GroundhoggBookingCalendar;


use Groundhogg\Base_Object;
use Groundhogg\Contact;
use Groundhogg\Email;
use Groundhogg\Event;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use GroundhoggBookingCalendar\Classes\Synced_Event;
use GroundhoggBookingCalendar\Connections\Zoom;
use GroundhoggSMS\Classes\SMS;
use GroundhoggSMS\SMS_Services;
use mysql_xdevapi\Exception;
use function Groundhogg\admin_page_url;
use function Groundhogg\array_map_keys;
use function Groundhogg\array_map_to_class;
use function Groundhogg\do_replacements;
use function Groundhogg\emergency_init_dbs;
use function Groundhogg\get_array_var;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_db;
use function Groundhogg\get_default_from_email;
use function Groundhogg\get_default_from_name;
use function Groundhogg\get_email_templates;
use function Groundhogg\get_form_list;
use function Groundhogg\get_object_ids;
use function Groundhogg\get_request_query;
use function Groundhogg\get_request_var;
use function Groundhogg\get_url_var;
use function Groundhogg\groundhogg_url;
use function Groundhogg\is_option_enabled;
use function Groundhogg\isset_not_empty;
use function Groundhogg\key_to_words;
use function Groundhogg\words_to_key;
use function GroundhoggSMS\send_sms;
use function GroundhoggSMS\validate_mobile_number;

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
 * @return Google_Calendar
 */
function google_calendar() {
	_doing_it_wrong( 'google_calendar', 'Dont use this function', '2.2' );

	return null;
}

function convert_to_client_timezone( $time, $timezone = '' ) {
	if ( ! $timezone ) {
		return $time;
	}

	if ( current_user_can( 'edit_calendar' ) ) {
		$local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_local_time( $time );

		return $local_time;
	}

	try {
		$local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_foreign_time( $time, $timezone );
	} catch ( \Exception $e ) {
		// Use site time anyway.
		$local_time = \Groundhogg\Plugin::$instance->utils->date_time->convert_to_local_time( $time );
	}

	return $local_time;
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
 * Adjust shades of colour for front end calendar
 *
 * @param $hex
 * @param $steps
 *
 * @return string
 */
function adjust_brightness( $hex, $steps ) {

	// Steps should be between -255 and 255. Negative = darker, positive = lighter
	$steps = max( - 255, min( 255, $steps ) );

	// Normalize into a six character long hex string
	$hex = str_replace( '#', '', $hex );
	if ( strlen( $hex ) == 3 ) {
		$hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
	}

	// Split into three parts: R, G and B
	$color_parts = str_split( $hex, 2 );
	$return      = '#';

	foreach ( $color_parts as $color ) {
		$color  = hexdec( $color ); // Convert to decimal
		$color  = max( 0, min( 255, $color + $steps ) ); // Adjust color
		$return .= str_pad( dechex( $color ), 2, '0', STR_PAD_LEFT ); // Make two char hex code
	}

	return $return;
}

/**
 * Schedule a 1 off reminder notification
 *
 * @param     $email_id       int the ID of the email to send
 * @param     $appointment_id int|string the ID of the appointment being referenced
 * @param int $time           time time to send at, defaults to time()
 *
 * @return bool whether the scheduling was successful.
 */
function send_email_reminder_notification( $email_id = 0, $appointment_id = 0, $time = 0 ) {

	if ( ! $email_id || ! $appointment_id ) {
		return false;
	}

	$appointment = is_int( $appointment_id ) ? new Appointment( $appointment_id ) : $appointment_id;
	$email       = is_int( $email_id ) ? new Email( $email_id ) : $email_id;

	if ( ! $appointment->exists() || ! $email->exists() ) {
		return false;
	}

	if ( ! $time ) {
		$time = time();
	}

	$event = new Event( [
		'time'       => $time,
		'funnel_id'  => $appointment->get_id(),
		'step_id'    => $email->get_id(),
		'contact_id' => $appointment->get_contact_id(),
		'event_type' => Email_Reminder::NOTIFICATION_TYPE,
		'status'     => 'waiting',
	], 'event_queue' );

	if ( ! $event->exists() ) {
		return false;
	}

	do_action( 'groundhogg/calendar/reminder_scheduled', $event );

	return true;

}


/**
 * Setup the step for an event as the Reminder notification type
 *
 * @param $event Event
 */
function setup_reminder_notification_object( $event ) {

	// Only if SMS is enabled, otherwise use email notification
	if ( $event->get_event_type() === SMS_Reminder::NOTIFICATION_TYPE && is_sms_plugin_active() ) {

		// Step ID will be the ID of the email
		// Funnel ID will be the ID of the appointment
		$event->step = new SMS_Reminder( $event->get_funnel_id(), $event->get_step_id() );

		return;
	}

	// Step ID will be the ID of the email
	// Funnel ID will be the ID of the appointment
	$event->step = new Email_Reminder( $event->get_funnel_id(), $event->get_step_id() );

}

add_action( 'groundhogg/event/post_setup', __NAMESPACE__ . '\setup_reminder_notification_object' );

/**
 * Schedule a 1 off reminder notification
 *
 * @param     $sms_id         int the ID of the email to send
 * @param     $appointment_id int|string the ID of the appointment being referenced
 * @param int $time           time time to send at, defaults to time()
 *
 * @return bool whether the scheduling was successful.
 */
function send_sms_reminder_notification( $sms_id = 0, $appointment_id = 0, $time = 0 ) {

	if ( ! $sms_id || ! $appointment_id || ! is_sms_plugin_active() ) {
		return false;
	}

	$appointment = is_int( $appointment_id ) ? new Appointment( absint( $appointment_id ) ) : $appointment_id;
	$sms         = is_int( $sms_id ) ? new SMS( absint( $sms_id ) ) : $sms_id;

	if ( ! $appointment->exists() || ! $sms->exists() ) {
		return false;
	}

	if ( ! $time ) {
		$time = time();
	}

	$event = new Event( [
		'time'       => $time,
		'funnel_id'  => $appointment->get_id(),
		'step_id'    => $sms->get_id(),
		'contact_id' => $appointment->get_contact_id(),
		'event_type' => SMS_Reminder::NOTIFICATION_TYPE,
		'status'     => 'waiting',
	], 'event_queue' );

	if ( ! $event->exists() ) {
		return false;
	}

	return true;

}


function is_sms_plugin_active() {
	return \Groundhogg\is_sms_plugin_active();
}


function add_booking_appointment() {
	?>
    <table class="form-table">
        <tr>
            <th><?php _ex( 'Book Appointment', 'contact_record', 'groundhogg-calendar' ); ?></th>
            <td>
                <div style="max-width: 400px;">
					<?php
					$calendars = get_calendar_list();
					echo Plugin::$instance->utils->html->select2( [
						'name'        => 'appointment_booking_from_contact',
						'id'          => 'appointment_booking_from_contact',
						'class'       => 'appointment_booking_from_contact gh-select2',
						'data'        => $calendars,
						'multiple'    => false,
						'placeholder' => __( 'Please select a calendar', 'groundhogg-calendar' ),
					] );
					?>
                    <div class="row-actions">
                        <button type="submit" name="appointment_book" value="appointment_book"
                                class="button"><?php _e( 'Book Appointment', 'groundhogg-calendar' ); ?></button>
                    </div>
                </div>
            </td>
        </tr>
    </table>
	<?php
}

add_action( 'groundhogg/admin/contact/record/tab/actions', __NAMESPACE__ . '\add_booking_appointment', 12 );


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
 * Action for adding an appointment via the contact screen
 *
 * @param $contact_id
 * @param $contact
 */
function display_calendar_contact( $contact_id, $contact ) {
	if ( get_request_var( 'appointment_book' ) ) {
		wp_safe_redirect( admin_page_url( 'gh_calendar', [
			'action'   => 'edit',
			'contact'  => $contact_id,
			'calendar' => absint( get_request_var( 'appointment_booking_from_contact' ) ),
		] ) );
	}
}

add_action( 'groundhogg/admin/contact/save', __NAMESPACE__ . '\display_calendar_contact', 10, 2 );

/**
 * Replace anything not in alpha numeric with a blank.
 *
 * @return array|string|string[]|null
 */
function generate_uuid() {
	return preg_replace( "/[^A-z0-9]/", "", wp_generate_uuid4() );
}

/**
 * Convert a duration to human readable format.
 *
 * @since 5.1.0
 *
 * @param string $duration Duration will be in string format (HH:ii:ss) OR (ii:ss),
 *                         with a possible prepended negative sign (-).
 *
 * @return string|false A human readable duration string, false on failure.
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
 * @param $appointment Appointment
 * @param $status      string
 *
 * @return bool
 */
function send_appointment_admin_notifications( $appointment, $status ) {

	$status_i18n = [
		Email_Reminder::SCHEDULED   => __( 'Appointment Scheduled', 'groundhogg-appointments' ),
		Email_Reminder::RESCHEDULED => __( 'Appointment Rescheduled', 'groundhogg-appointments' ),
		Email_Reminder::CANCELLED   => __( 'Appointment Cancelled', 'groundhogg-appointments' ),
	];

	\GroundhoggBookingCalendar\Plugin::$instance->replacements->set_appointment( $appointment );

	$content = $appointment->get_calendar()->get_meta( 'notification' );
	$content = sanitize_textarea_field( do_replacements( $content, $appointment->get_contact() ) );
	$content .= "\n" . sprintf( __( "Status: %s", 'groundhogg-calendar' ), $status_i18n[$status] );

	$subject = $appointment->get_calendar()->get_meta( 'subject' );
	$subject = sanitize_text_field( do_replacements( $subject, $appointment->get_contact() ) );
	$subject = sprintf( "%s: %s", $status_i18n[$status], $subject );

	$headers = [
		sprintf( 'From: %s <%s>', get_default_from_name(), get_default_from_email() )
	];

	$email_recipients = do_replacements( $appointment->get_calendar()->get_meta( 'admin_notification_email_recipients' ) ?: '{calendar_owner_email}', $appointment->get_contact() );
	$sent             = \Groundhogg_Email_Services::send_transactional( $email_recipients, $subject, $content, $headers );

	// If the SMS plugin is active and sms notifications are enabled let's also send this as an SMS
	if ( is_sms_plugin_active() && $appointment->get_calendar()->is_admin_notification_enabled( 'sms' ) ) {
		$sms_recipients = do_replacements( $appointment->get_calendar()->get_meta( 'admin_notification_sms_recipients' ) ?: '{calendar_owner_phone}', $appointment->get_contact() );
		$sent           = SMS_Services::send_transactional( $sms_recipients, $content ) && $sent;
	}

	\GroundhoggBookingCalendar\Plugin::$instance->replacements->clear();

	return $sent;
}

/**
 * Get's the maximum booking period of all the calendars
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
		Email_Reminder::SCHEDULED   => $emails['scheduled']->get_id(),
		Email_Reminder::RESCHEDULED => $emails['rescheduled']->get_id(),
		Email_Reminder::CANCELLED   => $emails['cancelled']->get_id(),
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
			SMS_Reminder::SCHEDULED   => $sms['scheduled']->get_id(),
			SMS_Reminder::RESCHEDULED => $sms['rescheduled']->get_id(),
			SMS_Reminder::CANCELLED   => $sms['cancelled']->get_id(),
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
		'sms'                       => is_sms_plugin_active(),
		Email_Reminder::SCHEDULED   => true,
		Email_Reminder::RESCHEDULED => true,
		Email_Reminder::CANCELLED   => true,
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
