<?php
/*
Plugin Name: Groundhogg Appointments
Plugin URI: https://www.groundhogg.io/downloads/booking-calendar/
Description: Create calendars and appointments.
Version: 1.0.4
Author: Groundhogg Inc.
Author URI: http://www.groundhogg.io
Text Domain: groundhogg
Domain Path: /languages
*/

if ( ! class_exists( 'GH_APPOINTMENTS' ) ) :

class GH_APPOINTMENTS
{

    public $ID = 3461;
    public $name = 'appointments';
    public $version = '1.0.4';
    public $author = 'Groundhogg Inc.';
    /**
     * @var WPGH_DB_Calendar_Meta
     */
    public $calendarmeta;

    /**
     * @var WPGH_DB_Appointment_Meta
     */
    public $appointmentmeta;

    /**
     * @var WPGH_DB_Appointments
     */
    public $appointments;

    /**
     * @var WPGH_DB_Calendar
     */
    public $calendar;

    /**
     * @var WPGH_Extension
     */
    public $extension;

    /**
     * @var GH_APPOINTMENTS
     */
    public static $instance;

    /**
     * @var WPGH_Calendar_Page
     */
    public $page;


    /**
     * @var WPGH_Roles_Calendar
     */
    public $role_calendar;

    /**
     * @var bool
     */
    public static $is_setup = false;

    /**
     * @var WPGH_Pipeline_Replacements
     */
    public $pipeline;

    /**
     * @var WPGH_Appointment_settings_Tab
     */
    public $settings;

    /**
     * @var WPGH_Appointment_Benchmark
     */
    public $benchmark;

    /**
     * @var WPGH_Appointment_Shortcode
     */
    public $shortcode;

    /**
     * @var WPGH_Google_Calendar
     */
    public $google_calendar;

    /**
     * create object
     *
     * @return GH_APPOINTMENTS
     */
    public static function instance()
    {
        if ( !self::$is_setup ) {

            self::$is_setup = true;
            self::$instance = new GH_APPOINTMENTS;

            self::$instance->setup_constants();
            self::$instance->add_extension();

            self::$instance->includes();

            self::$instance->calendarmeta     = new WPGH_DB_Calendar_Meta();
            self::$instance->appointmentmeta  = new WPGH_DB_Appointment_Meta();
            self::$instance->calendar         = new WPGH_DB_Calendar();
            self::$instance->appointments     = new WPGH_DB_Appointments();
            self::$instance->role_calendar    = new WPGH_Roles_Calendar();
            self::$instance->benchmark        = new WPGH_Appointment_Benchmark();
            self::$instance->shortcode        = new WPGH_Appointment_Shortcode();
            self::$instance->pipeline         = new WPGH_Pipeline_Replacements();
            self::$instance->google_calendar  = new WPGH_Google_Calendar();

            if ( is_admin() ){
                self::$instance->page             = new WPGH_Calendar_Page();
                self::$instance->settings         = new WPGH_Appointment_Settings_Tab();
            }

        }
        return self::$instance;
    }

    /**
     * Setup the constants
     */

    private function setup_constants()
    {
        if ( ! defined( 'WPGH_APPOINTMENT_ID' ) ) {
            define('WPGH_APPOINTMENT_ID', $this->ID);
        }
        if ( ! defined( 'WPGH_APPOINTMENT_NAME' ) ) {
            define( 'WPGH_APPOINTMENT_NAME', $this->name );
        }
        if ( ! defined( 'WPGH_APPOINTMENT_VERSION' ) ) {
            define( 'WPGH_APPOINTMENT_VERSION', $this->version );
        }
        if ( ! defined( 'WPGH_APPOINTMENT_PLUGIN_URI' ) ) {
            define( 'WPGH_APPOINTMENT_PLUGIN_URI', plugins_url( '/', __FILE__ ) );
        }
        if ( ! defined( 'WPGH_APPOINTMENT_PLUGIN_DIR' ) ) {
            define( 'WPGH_APPOINTMENT_PLUGIN_DIR', plugin_dir_path(__FILE__ ) );
        }
        if ( ! defined( 'WPGH_APPOINTMENT_PLUGIN_FILE' ) ){
            define( 'WPGH_APPOINTMENT_PLUGIN_FILE', __FILE__ );
        }
        if ( ! defined( 'WPGH_APPOINTMENT_ASSETS_FOLDER' ) ){
            define( 'WPGH_APPOINTMENT_ASSETS_FOLDER', plugin_dir_url( __FILE__ ) . 'assets/' );
        }
    }

    /**
     * Add the extension for licensing.
     */
    private function add_extension()
    {
        self::$instance->extension = new WPGH_Extension(
            $this->ID,
            $this->name,
            __FILE__,
            $this->version,
            $this->author,
            '',
            'Run automation based on Groundhogg Appointments.'
        );
    }

    /**
     * includes all  the essential files.
     */
    public function includes()
    {
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-db-appointmentmeta.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/install.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-google-calendar.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-roles-calendar.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-db-calendarmeta.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-db-appointment.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-db-calendar.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-appointment-shortcode.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/class-wpgh-pipeline-replacements.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/admin/class-wpgh-calendar-page.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/admin/class-wpgh-appointment-settings-tab.php';
        require_once WPGH_APPOINTMENT_PLUGIN_DIR  . 'includes/admin/class-wpgh-appointment-benchmark.php';

    }
}
endif;
function WPGH_APPOINTMENTS()
{
    return GH_APPOINTMENTS::instance();
}

if (!class_exists('WPGH_Extension_Manager')) {
    add_action('groundhogg_loaded', 'WPGH_APPOINTMENTS');
} else {
    WPGH_APPOINTMENTS();
}