<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Admin\Admin_Menu;
use Groundhogg\DB\Manager;
use Groundhogg\Extension;
use GroundhoggBookingCalendar\Admin\Testing\Test_Page;
use GroundhoggBookingCalendar\Api\Calendar_Api;
use GroundhoggBookingCalendar\DB\Appointment_Meta;
use GroundhoggBookingCalendar\DB\Appointments;
use GroundhoggBookingCalendar\DB\Calendar_Meta;
use GroundhoggBookingCalendar\DB\Calendars;
use GroundhoggBookingCalendar\Admin\Calendars\Calendar_Page;


class Plugin extends Extension
{

    /**
     * @var Google_Calendar
     */
    public $google_calendar;

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
    public function includes()
    {
        include dirname( __FILE__ ) . '/functions.php';
    }

    /**
     * Get the ID number for the download in EDD Store
     *
     * @return int
     */
    public function get_download_id()
    {
        return 3461;
    }

    /**
     * Init any components that need to be added.
     *
     * @return void
     */
    public function init_components()
    {
        $this->google_calendar = new Google_Calendar();
        $this->updater = new Updater();
        $this->roles = new Roles();
        $this->installer = new Installer();
        $this->shortcode = new Shortcode();

        new Rewrites();
    }

    public function register_admin_pages( $admin_menu )
    {
//        $admin_menu->calendartest = new Test_Page();
        $admin_menu->calendar = new Calendar_Page();
    }


    /**
     * Register the new DB.
     *
     * @param Manager $db_manager
     */
    public function register_dbs( $db_manager )
    {
        $db_manager->appointments = new Appointments();
        $db_manager->appointmentmeta = new Appointment_Meta();
        $db_manager->calendars = new Calendars();
        $db_manager->calendarmeta = new Calendar_Meta();
    }


    public function register_admin_scripts( $is_minified, $IS_MINIFIED )
    {
        wp_register_script( 'jstz', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/jstz.min.js', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
        wp_register_script( 'groundhogg-appointments-admin', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/js/admin.new.js', [ 'jquery', 'groundhogg-admin-functions' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
//        wp_register_script( 'groundhogg-appointments-shortcode', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/js/shortcode.js', ['jquery', 'jquery-ui-datepicker' ],GROUNDHOGG_BOOKING_CALENDAR_VERSION  );
        wp_register_script( 'fullcalendar-moment', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/lib/fullcalendar/lib/moment.min.js', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
        wp_register_script( 'fullcalendar-main', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/lib/fullcalendar/fullcalendar.js', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );

//        wp_register_script( 'groundhogg-appointments-appointments', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/js/appointments.js', [ 'jstz', 'jquery', 'jquery-ui-datepicker' ],GROUNDHOGG_BOOKING_CALENDAR_VERSION  );
//        wp_localize_script( 'groundhogg-appointments-appointments','BookingCalendar', [
//            'ajax_url'          => admin_url( 'admin-ajax.php' ),
//            'invalidDateMsg'    => __( 'Please select a time slot first.', 'groundhogg' ),
//            'invalidDetailsMsg' => __( 'Please make sure all your details are filled out.', 'groundhogg' ),
//            'invalidEmailMsg'   => __( 'Your email address is invalid.', 'groundhogg' ),
//        ] );
    }

    public function register_frontend_scripts( $is_minified, $IS_MINIFIED )
    {
        wp_register_script( 'jstz', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/jstz.min.js', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
        wp_register_script( 'groundhogg-appointments-frontend', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/js/frontend.new.js', [ 'jstz', 'jquery', 'jquery-ui-datepicker' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
    }

    public function register_admin_styles()
    {
        wp_register_style( 'groundhogg-fullcalendar', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/lib/fullcalendar/fullcalendar.css', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
        wp_register_style( 'groundhogg-calender-admin', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/css/backend.css', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
    }

    public function register_frontend_styles()
    {
        wp_register_style( 'jquery-ui', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/jquery-ui.min.css', [], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
        wp_register_style( 'jquery-ui-datepicker', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/calendar.css', [ 'jquery-ui' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
        wp_register_style( 'groundhogg-calendar-frontend', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/frontend.css', [ 'jquery-ui-datepicker' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION );

    }

    public function register_apis( $api_manager )
    {
        $api_manager->calendar_api = new Calendar_Api();
    }

    /**
     * Register controls to retrive google ID and secret
     *
     * @param array[] $settings
     * @return array[]
     */

    public function register_settings( $settings )
    {
        $settings[ 'gh_google_calendar_client_id' ] = [
            'id' => 'gh_google_calendar_client_id',
            'section' => 'google_calendar',
            'label' => __( 'Client ID', 'groundhogg' ),
            'desc' => __( 'Your Google developer client ID.', 'groundhogg' ),
            'type' => 'input',
            'atts' => [
                'name' => 'gh_google_calendar_client_id',
                'id' => 'gh_google_calendar_client_id',
            ]
        ];
        $settings[ 'gh_google_calendar_secret_key' ] = [
            'id' => 'gh_google_calendar_secret_key',
            'section' => 'google_calendar',
            'label' => __( 'Secret Key', 'groundhogg' ),
            'desc' => __( 'Your Google developer Secret Key.', 'groundhogg' ),
            'type' => 'input',
            'atts' => [
                'name' => 'gh_google_calendar_secret_key',
                'id' => 'gh_google_calendar_secret_key',
            ]
        ];

        return $settings;
    }

    /**
     * Register setting tab for calendar
     *
     * @param array[] $tabs
     * @return array[]
     */
    public function register_settings_tabs( $tabs )
    {
        $tabs[ 'calendar' ] = [
            'id' => 'calendar',
            'title' => _x( 'Booking Calendar', 'settings_tabs', 'groundhogg' )
        ];

        return $tabs;
    }

    /**
     * Register Booking calendar setting section
     *
     * @param array[] $sections
     * @return array[]
     */
    public function register_settings_sections( $sections )
    {
        $sections[ 'google_calendar' ] = [
            'id' => 'google_calendar',
            'title' => _x( 'Google API Keys', 'settings_sections', 'groundhogg' ),
            'tab' => 'calendar'
        ];

        return $sections;
    }


    /**
     * Get the version #
     *
     * @return mixed
     */
    public function get_version()
    {
        return GROUNDHOGG_BOOKING_CALENDAR_VERSION;
    }

    /**
     * @return string
     */
    public function get_plugin_file()
    {
        return GROUNDHOGG_BOOKING_CALENDAR__FILE__;
    }

    /**
     * Register autoloader.
     *
     * Groundhogg autoloader loads all the classes needed to run the plugin.
     *
     * @since 1.6.0
     * @access private
     */
    protected function register_autoloader()
    {
        require GROUNDHOGG_BOOKING_CALENDAR_PATH . 'includes/autoloader.php';
        Autoloader::run();
    }
}

Plugin::instance();