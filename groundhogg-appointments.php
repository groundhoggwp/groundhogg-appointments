<?php
/*
Plugin Name: Groundhogg-Appoinments
Description: Create calendar and Appointments for  contact.
Version: 1.0
Author: Groundhogg Inc.
Author URI: http://www.groundhogg.io
Text Domain: groundhogg
Domain Path: /languages
*/


class GH_APPOINTMENTS
{
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

    public function __construct()
    {
        $this->includes();
        $this->calendarmeta     = new WPGH_DB_Calendar_Meta();
        $this->appointmentmeta  = new WPGH_DB_Appointment_Meta();
        $this->calendar         = new WPGH_DB_Calendar();
        $this->appointments     = new WPGH_DB_Appointments();

        // create object of admin page

        if (!$this->calendarmeta->installed()) {
            $this->calendarmeta->create_table();
        }
        if (!$this->appointmentmeta->installed()) {
            $this->appointmentmeta->create_table();
        }
        if (!$this->appointments->installed()) {
            $this->appointments->create_table();
        }
        if (!$this->calendar->installed()) {
            $this->calendar->create_table();
        }

        $page  = new WPGH_Calendar_Page();
    }

    public function includes()
    {
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-appointmentmeta.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-calendarmeta.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-appointment.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-calendar.php';
        require_once dirname(__FILE__) . '/includes/admin/class-wpgh-calendar-page.php';

    }
}

function WPGH_APPOINTMENTS()
{
    return new GH_APPOINTMENTS();
}

if (!class_exists('WPGH_Extension_Manager')) {
    add_action('groundhogg_loaded', 'WPGH_APPOINTMENTS');
} else {
    WPGH_APPOINTMENTS();
}