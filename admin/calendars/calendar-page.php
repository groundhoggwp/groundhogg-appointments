<?php

namespace GroundhoggBookingCalendar\Admin\Calendars;

use Exception;
use Groundhogg\Admin\Admin_Page;
use Groundhogg\Base_Object;
use Groundhogg\Email;
use GroundhoggBookingCalendar\Classes\Email_Reminder;
use GroundhoggBookingCalendar\Classes\Google_Calendar;
use GroundhoggBookingCalendar\Classes\Google_Connection;
use GroundhoggBookingCalendar\Classes\SMS_Reminder;
use GroundhoggSMS\Classes\SMS;
use WP_Error;
use function Groundhogg\admin_page_url;
use function Groundhogg\current_user_is;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\get_email_templates;
use function Groundhogg\get_post_var;
use function Groundhogg\get_request_var;
use function Groundhogg\get_url_var;
use function Groundhogg\groundhogg_url;
use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;
use function Groundhogg\is_replacement_code_format;
use function Groundhogg\validate_mobile_number;
use function GroundhoggBookingCalendar\google;
use function GroundhoggBookingCalendar\google_calendar;
use function GroundhoggBookingCalendar\in_between_inclusive;
use function GroundhoggBookingCalendar\is_sms_plugin_active;
use function GroundhoggBookingCalendar\zoom;


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Calendar_Page
 *
 * @package GroundhoggBookingCalendar\Admin\Calendars
 */
class Calendar_Page extends Admin_Page {

	public function help() {
		// TODO: Implement help() method.
	}

	protected function add_additional_actions() {
		// TODO: Implement add_additional_actions() method.
	}

	protected function add_ajax_actions() {
		add_action( 'wp_ajax_groundhogg_get_appointments', [ $this, 'get_appointments_ajax' ] );
		add_action( 'wp_ajax_groundhogg_add_appointments', [ $this, 'add_appointment_ajax' ] );
		add_action( 'wp_ajax_groundhogg_update_appointments', [ $this, 'update_appointment_ajax' ] );
//        add_action( 'wp_ajax_groundhogg_verify_google_calendar', [ $this, 'verify_code_ajax' ] );


	}

	/**
	 * Process AJAX code for fetching appointments.
	 */
	public function get_appointments_ajax() {

		if ( ! current_user_can( 'add_appointment' ) ) {
			wp_send_json_error();
		}

		$ID = absint( get_request_var( 'calendar' ) );

		$calendar = new Calendar( $ID );

		if ( ! $calendar->exists() ) {
			wp_send_json_error();
		}

		$date = get_request_var( 'date' );

		$slots = $calendar->get_appointment_slots( $date );

		if ( empty( $slots ) ) {
			wp_send_json_error( __( 'No slots available.', 'groundhogg-calendar' ) );
		}

		wp_send_json_success( [ 'slots' => $slots ] );
	}


	/**
	 * Process AJAX call for adding appointments
	 */
	public function add_appointment_ajax() {

		if ( ! current_user_can( 'add_appointment' ) ) {
			wp_send_json_error();
		}

		$calendar = new Calendar( absint( get_request_var( 'calendar_id' ) ) );
		if ( ! $calendar->exists() ) {
			wp_send_json_error( __( 'Calendar not found!', 'groundhogg-calendar' ) );
		}

		$contact = get_contactdata( absint( get_request_var( 'contact_id' ) ) );
		if ( ! $contact->exists() ) {
			wp_send_json_error( __( 'Contact not found!', 'groundhogg-calendar' ) );
		}

		$start = absint( get_request_var( 'start_time' ) );
		$end   = absint( get_request_var( 'end_time' ) );

		if ( ! $start || ! $end ) {
			wp_send_json_error( __( 'Please provide a valid date selection.', 'groundhogg-calendar' ) );
		}

		$appointment = $calendar->schedule_appointment( [
			'contact_id' => $contact->get_id(),
			'name'       => sanitize_text_field( get_request_var( 'appointment_name' ) ),
			'start_time' => absint( $start ),
			'end_time'   => absint( $end ),
			'notes'      => sanitize_textarea_field( get_request_var( 'notes' ) )
		] );

		if ( ! $appointment->exists() ) {
			wp_send_json_error( __( 'Something went wrong while creating appointment.', 'groundhogg-calendar' ) );
		}

		$response = [
			'appointment' => $appointment->get_for_full_calendar(),
			'msg'         => __( 'Appointment booked successfully.', 'groundhogg-calendar' ),
			'url'         => admin_page_url( 'gh_contacts', [
				'action'  => 'edit',
				'contact' => $appointment->get_contact_id(),
			] )
		];

		wp_send_json_success( $response );
	}

	/**
	 * process AJAX request to update an appointment.
	 */
	public function update_appointment_ajax() {
		if ( ! current_user_can( 'edit_appointment' ) ) {
			wp_send_json_error();
		}

		// Handle update appointment
		$appointment = new Appointment( absint( get_request_var( 'id' ) ) );

		$status = $appointment->reschedule( [
			'start_time' => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( sanitize_text_field( get_request_var( 'start_time' ) ) ) ),
			'end_time'   => Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( sanitize_text_field( get_request_var( 'end_time' ) ) ) ),
		] );

		if ( ! $status ) {
			wp_send_json_error( __( 'Something went wrong while updating appointment.', 'groundhogg-calendar' ) );
		}

		wp_send_json_success( [ 'msg' => __( 'Your appointment updated successfully!', 'groundhogg-calendar' ) ] );

	}

	public function get_slug() {
		return 'gh_calendar';
	}

	public function get_name() {
		return _x( 'Calendars', 'page_title', 'groundhogg-calendar' );
	}

	public function get_cap() {
		return 'view_appointment';
	}

	public function get_item_type() {
		return 'calendar';
	}

	public function get_priority() {
		return 48;
	}

	public function get_title_actions() {
		if ( current_user_is( 'sales_manager' ) ) {
			return [];
		} else {
			return parent::get_title_actions();
		}
	}


	/**
	 * enqueue editor scripts for full calendar
	 */
	public function scripts() {

		wp_enqueue_style( 'groundhogg-admin' );

		if ( ( $this->get_current_action() === 'edit' && get_url_var( 'tab' ) === 'view' ) ) {
			$calendar = new Calendar( absint( get_url_var( 'calendar' ) ) );
			wp_enqueue_script( 'groundhogg-appointments-admin' );
			wp_localize_script( 'groundhogg-appointments-admin', 'GroundhoggCalendar', [
				'calendar_id'   => absint( get_request_var( 'calendar' ) ),
				'start_of_week' => get_option( 'start_of_week' ),
				'min_date'      => $calendar->get_min_booking_period( true ),
				'max_date'      => $calendar->get_max_booking_period( true ),
				'disabled_days' => $calendar->get_dates_no_slots(),
				'tab'           => get_request_var( 'tab', 'view' ),
				'action'        => $this->get_current_action()
			] );
		}

		wp_enqueue_script( 'fullcalendar-moment' );
		wp_enqueue_script( 'fullcalendar-main' );

		// STYLES
		wp_enqueue_style( 'groundhogg-fullcalendar' );
		wp_enqueue_style( 'groundhogg-calender-admin' );
		wp_enqueue_style( 'jquery-ui' );
	}

	public function view() {
		if ( ! class_exists( 'Calendars_Table' ) ) {
			include dirname( __FILE__ ) . '/calendars-table.php';
		}

		$calendars_table = new Calendars_Table();
		$this->search_form( __( 'Search Calendars', 'groundhogg-calendar' ) );
		$calendars_table->prepare_items();
		$calendars_table->display();
	}

	public function edit() {
		if ( ! current_user_can( 'edit_calendar' ) ) {
			$this->wp_die_no_access();
		}
		include dirname( __FILE__ ) . '/edit.php';
	}

	public function add() {
		if ( ! current_user_can( 'add_calendar' ) ) {
			$this->wp_die_no_access();
		}

		include dirname( __FILE__ ) . '/add.php';
	}

	public function edit_appointment() {
		if ( ! current_user_can( 'view_appointment' ) ) {
			$this->wp_die_no_access();
		}
		include dirname( __FILE__ ) . '/../appointments/edit.php';
	}


	public function process_delete() {
		if ( ! current_user_can( 'delete_calendar' ) ) {
			$this->wp_die_no_access();
		}

		$calendar = new Calendar( get_request_var( 'calendar' ) );
		if ( ! $calendar->exists() ) {
			return new \WP_Error( 'failed', __( 'Operation failed Calendar not Found.', 'groundhogg-calendar' ) );
		}

		if ( $calendar->delete() ) {
			$this->add_notice( 'success', __( 'Calendar deleted successfully!' ), 'success' );
		}

		return true;
	}


	/**
	 * Process add calendar and redirect to settings tab on successful calendar creation.
	 *
	 * @return string|\WP_Error
	 */
	public function process_add() {

		if ( ! current_user_can( 'add_calendar' ) ) {
			$this->wp_die_no_access();
		}

		$name        = sanitize_text_field( get_request_var( 'name' ) );
		$description = sanitize_textarea_field( get_request_var( 'description' ) );

		if ( ( ! $name ) || ( ! $description ) ) {
			return new \WP_Error( 'no_data', __( 'Please enter name and description of calendar.', 'groundhogg-calendar' ) );
		}

		$owner_id = absint( get_request_var( 'owner_id', get_current_user_id() ) );
		$calendar = new Calendar( [
			'user_id'     => $owner_id,
			'name'        => $name,
			'description' => $description,
		] );

		if ( ! $calendar->exists() ) {
			return new \WP_Error( 'no_calendar', __( 'Something went wrong while creating calendar.', 'groundhogg-calendar' ) );
		}

		/* SET DEFAULTS */

		// max booking period in availability
		$calendar->update_meta( 'max_booking_period_count', absint( get_request_var( 'max_booking_period_count', 3 ) ) );
		$calendar->update_meta( 'max_booking_period_type', sanitize_text_field( get_request_var( 'max_booking_period_type', 'months' ) ) );

		//min booking period in availability
		$calendar->update_meta( 'min_booking_period_count', absint( get_request_var( 'min_booking_period_count', 0 ) ) );
		$calendar->update_meta( 'min_booking_period_type', sanitize_text_field( get_request_var( 'min_booking_period_type', 'days' ) ) );

		//set default settings
		$calendar->update_meta( 'slot_hour', 1 );
		$calendar->update_meta( 'message', __( 'Appointment booked successfully!', 'groundhogg-calendar' ) );

		// Create default emails...
		$templates = get_email_templates();

		// Booked
		$scheduled = new Email( [
			'title'     => $templates['scheduled']['title'],
			'subject'   => $templates['scheduled']['title'],
			'content'   => $templates['scheduled']['content'],
			'status'    => 'ready',
			'from_user' => $owner_id,
		] );

		$cancelled = new Email( [
			'title'     => $templates['cancelled']['title'],
			'subject'   => $templates['cancelled']['title'],
			'content'   => $templates['cancelled']['content'],
			'status'    => 'ready',
			'from_user' => $owner_id,
		] );

		$rescheduled = new Email( [
			'title'     => $templates['rescheduled']['title'],
			'subject'   => $templates['rescheduled']['title'],
			'content'   => $templates['rescheduled']['content'],
			'status'    => 'ready',
			'from_user' => $owner_id,
		] );

		$reminder = new Email( [
			'title'     => $templates['reminder']['title'],
			'subject'   => $templates['reminder']['title'],
			'content'   => $templates['reminder']['content'],
			'status'    => 'ready',
			'from_user' => $owner_id,
		] );

		$calendar->update_meta( 'email_notifications', [
			Email_Reminder::SCHEDULED   => $scheduled->get_id(),
			Email_Reminder::RESCHEDULED => $rescheduled->get_id(),
			Email_Reminder::CANCELLED   => $cancelled->get_id(),
		] );

		// set one hour before reminder by default

		$calendar->update_meta( 'email_reminders', [
			[
				'when'     => 'before',
				'period'   => 'hours',
				'number'   => 1,
				'email_id' => $reminder->get_id()
			]
		] );


		//Create default SMS
		if ( is_sms_plugin_active() ) {
			$sms_scheduled = new SMS( [
				'title'   => __( 'Appointment Scheduled', 'groundhogg-calendar' ),
				'message' => __( "Hey {first},\n\nThank you for booking an appointment.\n\nYour appointment will be from {appointment_start_time} to {appointment_end_time}.\n\nThank you!\n\n@ the {business_name} team", 'groundhogg-calendar' ),

			] );

			$sms_cancelled = new SMS( [
				'title'   => __( 'Appointment Cancelled', 'groundhogg-calendar' ),
				'message' => __( "Hey {first},\n\nYour appointment scheduled on {appointment_start_time} has been cancelled.\n\nYou can always book another appointment using our booking page.\n\nThank you!\n\n@ the {business_name} team", 'groundhogg-calendar' ),

			] );

			$sms_rescheduled = new SMS( [
				'title'   => __( 'Appointment Rescheduled', 'groundhogg-calendar' ),
				'message' => __( "Hey {first},\n\nWe successfully rescheduled your appointment. Your new appointment will be from {appointment_start_time} to {appointment_end_time}.\n\nThank you!\n\n@ the {business_name} team", 'groundhogg-calendar' ),

			] );

			$sms_reminder = new SMS( [
				'title'   => __( 'Appointment Reminder', 'groundhogg-calendar' ),
				'message' => __( "Hey {first},\n\nJust a friendly reminder that you have appointment coming up with us on {appointment_start_time} we look forward to seeing you then.\n\nThank you!\n\n@ the {business_name} team", 'groundhogg-calendar' ),
			] );

			$calendar->update_meta( 'sms_notifications', [
				SMS_Reminder::SCHEDULED   => $sms_scheduled->get_id(),
				SMS_Reminder::RESCHEDULED => $sms_rescheduled->get_id(),
				SMS_Reminder::CANCELLED   => $sms_cancelled->get_id(),
			] );

			// set one hour before reminder by default

			$calendar->update_meta( 'sms_reminders', [
				[
					'when'   => 'before',
					'period' => 'hours',
					'number' => 1,
					'sms_id' => $sms_reminder->get_id()
				]
			] );
		}

		// update meta data to get set sms
		$this->add_notice( 'success', __( 'New calendar created successfully!', 'groundhogg-calendar' ), 'success' );

		return admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar->get_id() . '&tab=settings' );

	}

	/**
	 * Handles button click for syncing calendar.
	 *
	 * @return bool|string|void|WP_Error
	 */
	public function process_google_sync() {
		if ( ! current_user_can( 'edit_appointment' ) ) {
			$this->wp_die_no_access();
		}
		$calendar = new Calendar( absint( get_request_var( 'calendar' ) ) );

		$status = google_calendar()->sync( $calendar->get_id() );

		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$this->add_notice( 'success', __( 'Appointments synced successfully!', 'groundhogg-calendar' ), 'success' );

		return admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar->get_id() . '&tab=view' );

	}

	/**
	 * Process update appointment request post by the edit_appointment page.
	 *
	 * @return bool|\WP_Error
	 */
	public function process_edit_appointment() {

		if ( ! current_user_can( 'edit_appointment' ) ) {
			$this->wp_die_no_access();
		}

		$appointment_id = absint( get_request_var( 'appointment' ) );
		if ( ! $appointment_id ) {
			return new \WP_Error( 'no_appointment', __( 'Appointment not found!', 'groundhogg-calendar' ) );
		}

		$appointment = new Appointment( $appointment_id );

		$contact_id = absint( get_request_var( 'contact_id' ) );

		if ( ! $contact_id ) {
			return new \WP_Error( 'no_contact', __( 'Contact with this appointment not found!', 'groundhogg-calendar' ) );
		}

		if ( ! ( get_request_var( 'start_date' ) === get_request_var( 'end_date' ) ) ) {
			return new \WP_Error( 'different_date', __( 'Start date and end date needs to be same.', 'groundhogg-calendar' ) );
		}

		//check appointment is in working hours.....
		$availability = $appointment->get_calendar()->get_todays_available_periods( get_request_var( 'start_date' ) );

		if ( empty( $availability ) ) {
			return new \WP_Error( 'not_available', __( 'This date is not available.', 'groundhogg-calendar' ) );
		}

		$start_time = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( get_request_var( 'start_date' ) . ' ' . get_request_var( 'start_time' ) ) );
		$end_time   = Plugin::$instance->utils->date_time->convert_to_utc_0( strtotime( get_request_var( 'end_date' ) . ' ' . get_request_var( 'end_time' ) ) );

		//check for times
		if ( $start_time > $end_time ) {
			return new \WP_Error( 'no_contact', __( 'End time can not be smaller then start time.', 'groundhogg-calendar' ) );
		}

		/**
		 * @var $appointments_table \GroundhoggBookingCalendar\DB\Appointments;
		 */
		$appointments_table = get_db( 'appointments' );

		if ( $appointments_table->appointments_exist_in_range_except_same_appointment( $start_time, $end_time, $appointment->get_calendar_id(), $appointment->get_id() ) ) {
			return new \WP_Error( 'appointment_clash', __( 'You already have an appointment in this time slot.', 'groundhogg-calendar' ) );
		}

		// updates current appointment with the updated details and updates google appointment
		$status = $appointment->reschedule( [
			'contact_id' => $contact_id,
			'name'       => sanitize_text_field( get_request_var( 'appointmentname' ) ),
			'start_time' => $start_time,
			'end_time'   => $end_time,
			'notes'      => sanitize_textarea_field( get_request_var( 'notes' ) )
		] );

		if ( ! $status ) {
			$this->add_notice( new \WP_Error( 'error', 'Something went wrong...' ) );
		} else {
			$this->add_notice( 'success', __( "Appointment updated!", 'groundhogg-calendar' ), 'success' );
		}

		return true;
	}

	/**
	 *  Delete appointment from database and google calendar if connected.
	 *
	 * @return string|\WP_Error
	 */
	public function process_delete_appointment() {
		if ( ! current_user_can( 'delete_appointment' ) ) {
			$this->wp_die_no_access();
		}

		$appointment_id = get_request_var( 'appointment' );
		if ( ! $appointment_id ) {
			return new \WP_Error( 'no_appointment_id', __( 'Appointment ID not found', 'groundhogg-calendar' ) );
		}

		$appointment = new Appointment( $appointment_id );
		if ( ! $appointment->exists() ) {
			wp_die( __( "Appointment not found!", 'groundhogg-calendar' ) );
		}

		$calendar_id = $appointment->get_calendar_id();

		$status = $appointment->delete();
		if ( ! $status ) {
			return new \WP_Error( 'delete_failed', __( 'Something went wrong while deleting appointment.', 'groundhogg-calendar' ) );
		} else {
			$this->add_notice( 'success', __( 'Appointment deleted!', 'groundhogg-calendar' ), 'success' );
		}

		return admin_url( 'admin.php?page=gh_calendar&calendar=' . $calendar_id . '&action=edit&tab=list' );
	}

	/**
	 * manage tab's post request by calling appropriate function.
	 *
	 * @return bool
	 */
	public function process_edit() {
		if ( ! current_user_can( 'edit_calendar' ) ) {
			$this->wp_die_no_access();
		}

		$tab = get_request_var( 'tab', 'view' );

		switch ( $tab ) {

			default:
			case 'view':
				// Update actions from View
				break;
			case 'settings':
				// Update Settings Page
				$this->update_calendar_settings();
				break;
			case 'availability':
				// Update Availability
				$this->update_availability();
				break;
			case 'emails':
				$this->update_emails();
				break;
			case 'notification':
				$this->update_admin_notification();
				break;
			case 'sms' :
				$this->update_sms();
				break;
			case 'list':
				break;
			case 'integration':
				$this->update_integration_settings();
				break;
		}

		return true;
	}

	/**
	 * Update the admin notification configuration
	 */
	protected function update_admin_notification() {

		if ( ! current_user_can( 'edit_calendar' ) ) {
			$this->wp_die_no_access();
		}

		$calendar = new Calendar( get_url_var( 'calendar' ) );

		$admin_notifications = [
			'sms'                       => (bool) get_post_var( 'sms_notifications' ),
			Email_Reminder::SCHEDULED   => (bool) get_post_var( 'scheduled_notification' ),
			Email_Reminder::RESCHEDULED => (bool) get_post_var( 'rescheduled_notification' ),
			Email_Reminder::CANCELLED   => (bool) get_post_var( 'cancelled_notification' ),
		];

		$calendar->update_meta( 'enabled_admin_notifications', $admin_notifications );

		// Validate and sanitize emails for email admin notifications
		$admin_email_recipients = get_post_var( 'admin_notification_email_recipients' );
		$admin_email_recipients = sanitize_text_field( $admin_email_recipients );
		$admin_email_recipients = array_map( 'trim', explode( ',', $admin_email_recipients ) );
		$admin_email_recipients = array_filter( $admin_email_recipients, function ( $email ) {
			return is_email( $email ) || is_replacement_code_format( $email );
		} );
		$admin_email_recipients = implode( ', ', $admin_email_recipients );
		$calendar->update_meta( 'admin_notification_email_recipients', $admin_email_recipients );

		// Validate and sanitize mobile numbers for SMS notifications
		$admin_sms_recipients = get_post_var( 'admin_notification_sms_recipients' );
		$admin_sms_recipients = sanitize_text_field( $admin_sms_recipients );
		$admin_sms_recipients = array_map( 'trim', explode( ',', $admin_sms_recipients ) );
		$admin_sms_recipients = array_filter( $admin_sms_recipients, function ( $number ) {
			return validate_mobile_number( $number ) || is_replacement_code_format( $number );
		} );
		$admin_sms_recipients = implode( ', ', $admin_sms_recipients );
		$calendar->update_meta( 'admin_notification_sms_recipients', $admin_sms_recipients );

		// Other notification stuff
		$calendar->update_meta( 'subject', sanitize_text_field( get_request_var( 'subject' ) ) );
		$calendar->update_meta( 'notification', sanitize_textarea_field( get_request_var( 'notification' ) ) );
	}

	protected function update_emails() {
		if ( ! current_user_can( 'edit_calendar' ) ) {
			$this->wp_die_no_access();
		}

		$calendar = new Calendar( get_request_var( 'calendar' ) );

		$calendar->update_meta( 'email_notifications', [
			Email_Reminder::SCHEDULED   => absint( get_post_var( Email_Reminder::SCHEDULED ) ),
			Email_Reminder::RESCHEDULED => absint( get_post_var( Email_Reminder::RESCHEDULED ) ),
			Email_Reminder::CANCELLED   => absint( get_post_var( Email_Reminder::CANCELLED ) )
		] );

		$reminders = get_post_var( 'email_reminders' );

		$operation = get_array_var( $reminders, 'when' );
		$number    = get_array_var( $reminders, 'number' );
		$period    = get_array_var( $reminders, 'period' );
		$email_id  = get_array_var( $reminders, 'email_id' );

		$reminders = [];

		if ( empty( $operation ) ) {
			$calendar->delete_meta( 'email_reminders' );
		} else {
			foreach ( $operation as $i => $op ) {
				$temp_reminders             = [];
				$temp_reminders['when']     = $op;
				$temp_reminders['number']   = $number[ $i ];
				$temp_reminders['period']   = $period[ $i ];
				$temp_reminders['email_id'] = $email_id [ $i ];
				$reminders[]                = $temp_reminders;
			}

			$calendar->update_meta( 'email_reminders', $reminders );
		}
	}

	/**
	 * Save SMS notifications configuration
	 */
	protected function update_sms() {
		if ( ! current_user_can( 'edit_calendar' ) ) {
			$this->wp_die_no_access();
		}

		$calendar = new Calendar( get_request_var( 'calendar' ) );

		$calendar->update_meta( 'enable_sms_notifications', (bool) get_post_var( 'sms_notification' ) );

		$calendar->update_meta( 'sms_notifications', [
			SMS_Reminder::SCHEDULED   => absint( get_request_var( SMS_Reminder::SCHEDULED ) ),
			SMS_Reminder::RESCHEDULED => absint( get_request_var( SMS_Reminder::RESCHEDULED ) ),
			SMS_Reminder::CANCELLED   => absint( get_request_var( SMS_Reminder::CANCELLED ) )
		] );

		$reminders = get_request_var( 'sms_reminders' );

		$operation = get_array_var( $reminders, 'when' );
		$number    = get_array_var( $reminders, 'number' );
		$period    = get_array_var( $reminders, 'period' );
		$sms_id    = get_array_var( $reminders, 'sms_id' );

		$reminder = [];
		if ( empty( $operation ) ) {
			$calendar->delete_meta( 'sms_reminders' );
		} else {

			foreach ( $operation as $i => $op ) {
				$temp_reminders           = [];
				$temp_reminders['when']   = $op;
				$temp_reminders['number'] = $number[ $i ];
				$temp_reminders['period'] = $period[ $i ];
				$temp_reminders['sms_id'] = $sms_id [ $i ];
				$reminder[]               = $temp_reminders;
			}
			$calendar->update_meta( 'sms_reminders', $reminder );
		}
	}


	/**
	 * Update calendar availability
	 */
	protected function update_availability() {

		if ( ! current_user_can( 'edit_calendar' ) ) {
			$this->wp_die_no_access();
		}

		$calendar_id = absint( get_request_var( 'calendar' ) );

		$calendar = new Calendar( $calendar_id );

		$rules = get_request_var( 'rules' );

		$days   = get_array_var( $rules, 'day' );
		$starts = get_array_var( $rules, 'start' );
		$ends   = get_array_var( $rules, 'end' );

		$availability = [];

		if ( ! $days ) {
			$this->add_notice( new \WP_Error( 'error', 'Please add at least one availability slot' ) );

			return;
		}

		foreach ( $days as $i => $day ) {

			$temp_rule          = [];
			$temp_rule['day']   = $day;
			$temp_rule['start'] = $starts[ $i ];
			$temp_rule['end']   = $ends[ $i ];

			$availability[] = $temp_rule;

		}

		$calendar->update_meta( 'max_booking_period_count', absint( get_request_var( 'max_booking_period_count', 3 ) ) );
		$calendar->update_meta( 'max_booking_period_type', sanitize_text_field( get_request_var( 'max_booking_period_type', 'months' ) ) );

		$calendar->update_meta( 'min_booking_period_count', absint( get_request_var( 'min_booking_period_count', 0 ) ) );
		$calendar->update_meta( 'min_booking_period_type', sanitize_text_field( get_request_var( 'min_booking_period_type', 'days' ) ) );


		$calendar->update_meta( 'rules', $availability );

		$this->add_notice( 'updated', __( 'Availability updated.' ) );
	}

	/**
	 *  Updates the calendar settings.
	 */
	protected function update_calendar_settings() {

		$calendar_id = absint( get_request_var( 'calendar' ) );
		$calendar    = new Calendar( $calendar_id );

		$args = array(
			'user_id'     => absint( get_request_var( 'owner_id', get_current_user_id() ) ),
			'name'        => sanitize_text_field( get_request_var( 'name', $calendar->get_name() ) ),
			'description' => sanitize_textarea_field( get_request_var( 'description' ) ),
		);

		if ( ! $calendar->update( $args ) ) {
			$this->add_notice( new \WP_Error( 'error', 'Unable to update calendar.' ) );

			return;
		}

		// Save 12 hour
		if ( get_request_var( 'time_12hour' ) ) {
			$calendar->update_meta( 'time_12hour', true );
		} else {
			$calendar->delete_meta( 'time_12hour' );
		}

		// Save appointment length
		$calendar->update_meta( 'slot_hour', absint( get_request_var( 'slot_hour', 0 ) ) );
		$calendar->update_meta( 'slot_minute', absint( get_request_var( 'slot_minute', 0 ) ) );

		// Save buffer time
		$calendar->update_meta( 'buffer_time', absint( get_request_var( 'buffer_time', 0 ) ) );

		// Save make me look busy
		$calendar->update_meta( 'busy_slot', absint( get_request_var( 'busy_slot', 0 ) ) );

		// save success message
		$calendar->update_meta( 'message', wp_kses_post( get_request_var( 'message' ) ) );

		//save default note
		$calendar->update_meta( 'default_note', sanitize_textarea_field( get_request_var( 'default_note' ) ) );

		// save thank you page
		$calendar->update_meta( 'redirect_link_status', absint( get_request_var( 'redirect_link_status' ) ) );
		$calendar->update_meta( 'redirect_link', esc_url( get_request_var( 'redirect_link' ) ) );

		$form_override = absint( get_request_var( 'override_form_id', 0 ) );
		$calendar->update_meta( 'override_form_id', $form_override );

		$this->add_notice( 'success', _x( 'Settings updated.', 'notice', 'groundhogg-calendar' ), 'success' );
	}

	public function update_integration_settings() {

		$calendar_id = absint( get_request_var( 'calendar' ) );
		$calendar    = new Calendar( $calendar_id );

		// save gcal
		$calendars_being_used = $calendar->get_google_calendar_list();
		$google_calendar_list = wp_parse_id_list( get_post_var( 'google_calendar_list', [] ) );

		// Turn of deselected calendars
		$to_turn_off_sync = array_diff( $calendars_being_used, $google_calendar_list );

		foreach ( $to_turn_off_sync as $google_calendar_id ){
			$gcal = new Google_Calendar( $google_calendar_id );
			$gcal->disable_sync();
		}

		// Turn on selected calendars
		foreach ( $google_calendar_list as $google_calendar_id ){
			$gcal = new Google_Calendar( $google_calendar_id );
			$gcal->enable_sync();
		}

		$calendar->update_meta( 'google_calendar_list', $google_calendar_list );
		$calendar->update_meta( 'google_appointment_name', sanitize_text_field( get_request_var( 'google_appointment_name' ) ) );
		$calendar->update_meta( 'google_appointment_description', sanitize_textarea_field( get_request_var( 'google_appointment_description' ) ) );

		// Google Meet Setting
		if ( get_request_var( 'google_meet_enable' ) ) {
			$calendar->update_meta( 'google_meet_enable', true );
		} else {
			$calendar->delete_meta( 'google_meet_enable' );
		}

		//save Zoom Meeting settings
		if ( $account_id = absint( get_post_var( 'google_account_id' ) ) ) {
			$calendar->set_google_connection_id( $account_id );
		}

		if ( $google_calendar_id = absint( get_post_var( 'google_calendar_id' ) ) ) {
			$calendar->update_meta( 'google_calendar_id', $google_calendar_id );
		}

		$this->add_notice( 'success', _x( 'Integrations updated.', 'notice', 'groundhogg-calendar' ), 'success' );
	}

	/**
	 * Redirects users to GOOGLE oauth authentication URL with all the details.
	 *
	 * @return string
	 */
	public function process_access_code() {

		$redirect_uri = admin_page_url( 'gh_calendar', [
			'action'   => 'verify_google_code',
			'calendar' => get_url_var( 'calendar' ),
			'_wpnonce' => wp_create_nonce()
		] );

		return add_query_arg( [ 'redirect_uri' => urlencode( $redirect_uri ) ], 'https://proxy.groundhogg.io/oauth/google/start/' );
	}

	/**
	 * Retrieves authentication code from the response url and creates authentication token for the GOOGLE.
	 *
	 * @return bool|WP_Error
	 */
	public function process_verify_google_code() {

		if ( ! get_request_var( 'code' ) ) {
			return new \WP_Error( 'no_code', __( 'Authentication code not found!', 'groundhogg-calendar' ) );
		}

		$auth_code   = get_url_var( 'code' );
		$calendar_id = absint( get_url_var( 'calendar' ) );
		$calendar    = new Calendar( $calendar_id );

		$connection = new Google_Connection();
		$connection->create_from_auth( $auth_code );

		$calendar->set_google_connection_id( $connection->get_id() );
		$calendar->set_google_calendar_id( $connection->account_email );
		$calendar->update_meta( 'google_calendar_list', [
			$connection->account_email
		] );

		$this->add_notice( 'success', __( 'Connection to Google calendar successfully completed!', 'groundhogg-calendar' ), 'success' );

		return admin_page_url( 'gh_calendar', [
			'action'   => 'edit',
			'calendar' => $calendar_id,
			'tab'      => 'integration'
		] );
	}


	/**
	 * Redirects users to ZOOM oauth authentication URL with all the details.
	 *
	 * @return string
	 */
	public function process_access_code_zoom() {

		$redirect_uri = admin_page_url( 'gh_calendar', [
			'action'   => 'verify_zoom_code',
			'calendar' => get_url_var( 'calendar' ),
			'_wpnonce' => wp_create_nonce()
		] );

		return add_query_arg( [ 'return' => urlencode( $redirect_uri ) ], 'https://proxy.groundhogg.io/oauth/zoom/start/' );
	}


	/**
	 * Retrieves authentication code from the response url and creates authentication token for the ZOOM.
	 *
	 * @return bool|WP_Error
	 */
	public function process_verify_zoom_code() {

		if ( ! get_request_var( 'code' ) ) {
			return new \WP_Error( 'no_code', __( 'Authentication code not found!', 'groundhogg-calendar' ) );
		}

		$auth_code   = get_request_var( 'code' );
		$calendar_id = absint( get_request_var( 'calendar' ) );
		$calendar    = new Calendar( $calendar_id );

		$account_id = zoom()->init_connection( $auth_code );

		if ( is_wp_error( $account_id ) ) {
			return $account_id;
		}

		$calendar->set_zoom_account_id( $account_id );

		$this->add_notice( 'success', __( 'Connection to zoom successfully completed!', 'groundhogg-calendar' ), 'success' );

		return admin_page_url( 'gh_calendar', [
			'action'   => 'edit',
			'calendar' => $calendar_id,
			'tab'      => 'integration'
		] );
	}
}