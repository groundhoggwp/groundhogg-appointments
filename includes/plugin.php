<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\DB\Manager;
use Groundhogg\Extension;
use GroundhoggBookingCalendar\Admin\Appointments\Appointments_Page;
use GroundhoggBookingCalendar\Admin\Cards\Appointment_Card;
use GroundhoggBookingCalendar\Api\Calendar_Api;
use GroundhoggBookingCalendar\DB\Appointment_Meta;
use GroundhoggBookingCalendar\DB\Appointments;
use GroundhoggBookingCalendar\DB\Calendar_Meta;
use GroundhoggBookingCalendar\DB\Calendars;
use GroundhoggBookingCalendar\Admin\Calendars\Calendar_Page;
use GroundhoggBookingCalendar\DB\Google_Calendars;
use GroundhoggBookingCalendar\DB\Google_Connections;
use GroundhoggBookingCalendar\DB\Synced_Events;
use GroundhoggBookingCalendar\Steps\Booking_Calendar;


class Plugin extends Extension {

	/**
	 * @var Replacements
	 */
	public $replacements;

	/**
	 * @var Rewrites
	 */
	public $rewrites;

	/**
	 * Override the parent instance.
	 *
	 * @var Plugin
	 */
	public static $instance;

	/**
	 * Include any files.
	 *
	 * @return void
	 */
	public function includes() {
		include __DIR__ . '/functions.php';
		include __DIR__ . '/template.php';
	}

	/**
	 * Register the codes...
	 *
	 * @param \Groundhogg\Replacements $replacements
	 */
	public function add_replacements( $replacements ) {

		$replacements->add_group( 'calendar', __( 'Calendar', 'groundhogg-calendar' ) );

		$codes = $this->replacements->get_replacements();

		foreach ( $codes as $code ) {
			$replacements->add( $code['code'], $code['callback'], $code['description'], $code['name'], 'calendar' );
		}
	}

	/**
	 * Get the ID number for the download in EDD Store
	 *
	 * @return int
	 */
	public function get_download_id() {
		return 3461;
	}

	/**
	 * Init any components that need to be added.
	 *
	 * @return void
	 */
	public function init_components() {
		$this->sync         = new Calendar_Sync();
		$this->roles        = new Roles();
		$this->shortcode    = new Shortcode();
		$this->replacements = new Replacements();
		$this->rewrites     = new Rewrites();
		$this->installer    = new Installer();
		$this->updater      = new Updater();

		new Reminders_And_Notifications();
		new Upgrade_Notice();
	}

	public function register_admin_pages( $admin_menu ) {
		$admin_menu->calendar     = new Calendar_Page();
		$admin_menu->appointments = new Appointments_Page();
	}

	/**
	 * register the new benchmark.
	 *
	 * @param \Groundhogg\Steps\Manager $manager
	 */
	public function register_funnel_steps( $manager ) {
		$manager->add_step( new Booking_Calendar() );
	}

	public function register_v4_apis( $api_manager ) {
		$api_manager->calendar_api = new Calendar_Api();
	}

	/**
	 * Register the new DB.
	 *
	 * @param Manager $db_manager
	 */
	public function register_dbs( $db_manager ) {
		$db_manager->appointments       = new Appointments();
		$db_manager->appointmentmeta    = new Appointment_Meta();
		$db_manager->calendars          = new Calendars();
		$db_manager->calendarmeta       = new Calendar_Meta();
		$db_manager->google_connections = new Google_Connections();
		$db_manager->google_calendars   = new Google_Calendars();
		$db_manager->synced_events      = new Synced_Events();
	}

	public function register_admin_scripts( $is_minified, $IS_MINIFIED ) {
		wp_register_script( 'groundhogg-appointments-reminders', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/reminders.js', [
			'jquery',
			'groundhogg-admin-modal'
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION, true );
		wp_register_script( 'groundhogg-sms-reminders', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/sms-reminders.js', [
			'jquery',
			'groundhogg-admin-modal'
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION, true );

		wp_register_script( 'fullcalendar-main', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'lib/fullcalendar/lib/main.min.js', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_script( 'jstz', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/jstz.min.js', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_script( 'groundhogg-new-appointment-admin', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/new-appointment.js', [
			'jquery',
			'jquery-ui-datepicker',
			'groundhogg-admin-functions',
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION, true );

		wp_register_script( 'groundhogg-appointments-admin', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/appointments.js', [
			'jquery',
			'fullcalendar-main',
			'groundhogg-admin-functions',
			'groundhogg-admin-components',
			'groundhogg-admin-element',
			'groundhogg-admin-notes',
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION, true );
	}

	public function register_frontend_scripts( $is_minified, $IS_MINIFIED ) {
		wp_register_script( 'jstz', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/jstz.min.js', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_script( 'groundhogg-calendar', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/calendar.js', [
			'jstz',
			'jquery',
			'groundhogg-frontend',
			'wp-i18n',
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
	}

	public function register_admin_styles() {

		wp_register_style( 'groundhogg-fullcalendar', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'lib/fullcalendar/lib/main.min.css', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_style( 'groundhogg-calender-admin', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/backend.css', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_style( 'groundhogg-new-appointment-admin', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/admin/new-appointment.css', [
			'groundhogg-loader'
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_style( 'groundhogg-appointments-admin', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/admin/appointments.css', [
			'groundhogg-loader',
			'groundhogg-admin-element',
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
	}

	public function register_frontend_styles() {
		wp_register_style( 'groundhogg-admin-element', GROUNDHOGG_ASSETS_URL . 'css/admin/elements.css', [], GROUNDHOGG_VERSION );

		wp_register_style( 'jquery-ui', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/jquery-ui.min.css', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_style( 'gh-jquery-ui-datepicker', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/calendar.css', [ 'jquery-ui' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
		wp_register_style( 'groundhogg-calendar-frontend', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/frontend.css', [
			'groundhogg-form',
			'groundhogg-loader'
		], GROUNDHOGG_BOOKING_CALENDAR_VERSION );

	}


	/**
	 * Get the version #
	 *
	 * @return mixed
	 */
	public function get_version() {
		return GROUNDHOGG_BOOKING_CALENDAR_VERSION;
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return GROUNDHOGG_BOOKING_CALENDAR__FILE__;
	}

	/**
	 * Register autoloader.
	 *
	 * Groundhogg autoloader loads all the classes needed to run the plugin.
	 *
	 * @since  1.6.0
	 * @access private
	 */
	protected function register_autoloader() {
		require __DIR__ . '/autoloader.php';
		require __DIR__ . '/lib/google/vendor/autoload.php';
		Autoloader::run();
	}

	/**
	 * Register any info cards example
	 *
	 * @param \Groundhogg\Admin\Contacts\Info_Cards $cards
	 */
	public function register_contact_info_cards( $cards ) {
		//new Appointment_Card($cards);
		wp_register_style( 'groundhogg-appointment-info-cards-css', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/admin/appointment-info-card.css', [], GROUNDHOGG_VERSION );

		$cards::register( 'appointment-new-info-card', 'Appointments', function ( $contact ) {
			include( __DIR__ . '/../admin/cards/appointmentlist.php' );
		} );
	}
}

Plugin::instance();
