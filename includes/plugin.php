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
use GroundhoggBookingCalendar\Steps\Booking_Calendar;


class Plugin extends Extension
{

    /**
     * @var Google_Calendar
     */
    public $google_calendar;

    /**
     * @var Replacements
     */
    public $replacements;

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
     * Register the codes...
     *
     * @param \Groundhogg\Replacements $replacements
     */
    public function add_replacements( $replacements )
    {
        $codes = $this->replacements->get_replacements();
        foreach ( $codes as $code ){
            $replacements->add( $code[ 'code' ],  $code[ 'callback' ],  $code[ 'description' ] );
        }
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
        $this->replacements = new Replacements();

        new Rewrites();
    }

    public function register_admin_pages( $admin_menu )
    {
        $admin_menu->calendar = new Calendar_Page();
    }

    /**
     * register the new benchmark.
     *
     * @param \Groundhogg\Steps\Manager $manager
     */
    public function register_funnel_steps( $manager )
    {
        $manager->add_step( new Booking_Calendar());
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
        wp_register_script( 'groundhogg-appointments-reminders', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'js/reminders.js', [ 'jquery', 'groundhogg-admin-modal' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION, true );

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
        wp_register_script( 'groundhogg-appointments-frontend', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . '/js/frontend.new.js', [ 'jstz', 'jquery', 'jquery-ui-datepicker', 'groundhogg-frontend' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION );
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
        wp_register_style( 'groundhogg-calendar-frontend', GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL . 'css/frontend.css', [ 'jquery-ui-datepicker', 'groundhogg-form' ], GROUNDHOGG_BOOKING_CALENDAR_VERSION );

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
     * Add email templates...
     *
     * @param $templates array
     * @return mixed
     */
    public function register_email_templates( $email_templates )
    {

        ob_start();

        include GROUNDHOGG_BOOKING_CALENDAR_PATH . '/templates/emails/approved.php';

        $email_templates['approved']['title'] = _x( "Appointment Approved", 'email_template_name', 'groundhogg' );
        $email_templates['approved']['description'] = _x( "Email sent when appointment is approved.", 'email_template_description', 'groundhogg' );
        $email_templates['approved']['content'] = ob_get_contents();

        ob_clean();


        ob_start();

        include GROUNDHOGG_BOOKING_CALENDAR_PATH . '/templates/emails/booked.php';

        $email_templates['booked']['title'] = _x( "Appointment Booked", 'email_template_name', 'groundhogg' );
        $email_templates['booked']['description'] = _x( "Email sent when appointment is booked.", 'email_template_description', 'groundhogg' );
        $email_templates['booked']['content'] = ob_get_contents();

        ob_clean();

        ob_start();

        include GROUNDHOGG_BOOKING_CALENDAR_PATH . '/templates/emails/cancelled.php';

        $email_templates['cancelled']['title'] = _x( "Appointment Cancelled", 'email_template_name', 'groundhogg' );
        $email_templates['cancelled']['description'] = _x( "Email sent when appointment is cancelled.", 'email_template_description', 'groundhogg' );
        $email_templates['cancelled']['content'] = ob_get_contents();

        ob_clean();


        ob_start();

        include GROUNDHOGG_BOOKING_CALENDAR_PATH . '/templates/emails/rescheduled.php';

        $email_templates['rescheduled']['title'] = _x( "Appointment Rescheduled", 'email_template_name', 'groundhogg' );
        $email_templates['rescheduled']['description'] = _x( "Email sent when appointment is rescheduled.", 'email_template_description', 'groundhogg' );
        $email_templates['rescheduled']['content'] = ob_get_contents();

        ob_clean();

        ob_start();

        include GROUNDHOGG_BOOKING_CALENDAR_PATH . '/templates/emails/reminder.php';

        $email_templates['reminder']['title'] = _x( "Appointment Reminder", 'email_template_name', 'groundhogg' );
        $email_templates['reminder']['description'] = _x( "Email sent when appointment is reminder.", 'email_template_description', 'groundhogg' );
        $email_templates['reminder']['content'] = ob_get_contents();

        ob_clean();

        return $email_templates;
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