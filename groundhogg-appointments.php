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

        add_action('wp_enqueue_scripts', array($this , 'my_scripts') );
        add_action( 'wp_ajax_gh_add_appointment_client', array($this, 'gh_add_appointment_client'));

        add_shortcode( 'gh_calendar', array( $this , 'gh_calendar_shortcode' ) ) ;


    }

    public function gh_add_appointment_client()
    {
        global $wpdb;
        $date = $_POST['date'];
        $calendar_id  = intval( $_POST['calendar'] );
        //get start time and end time from business hours  of a day
        $start_time  = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time',true);
        $end_time  = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'end_time',true);

        //GET AVAILABLE TIME IN DAY
        $end_time   = strtotime( $date .' '.$end_time );
        $start_time = strtotime( $date .' '.$start_time );

        // get appointments

        $appointments_table_name  = WPGH_APPOINTMENTS()->appointments->table_name;

        $appointments = $wpdb->get_results( "SELECT * FROM $appointments_table_name as a WHERE a.start_time >= $start_time and a.end_time <=  $end_time" );


        $response = array( 'msg' => json_encode($appointments ) );
        wp_die(json_encode($response));
    }

    public function my_scripts() {
        wp_enqueue_script( 'jquery', plugins_url( 'assets/js/lib/fullcalendar/lib/jquery.min.js', __FILE__ ), array('jquery') );
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui' );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'ajax-script', plugins_url( 'assets/js/load_appointment.js', __FILE__ ) , array('jquery') ,filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/load_appointment.js' ));
        wp_localize_script( 'ajax-script', 'ajax_object',array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'we_value' => 1234 ) );

    }





    public function includes()
    {
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-appointmentmeta.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-calendarmeta.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-appointment.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-calendar.php';
        require_once dirname(__FILE__) . '/includes/admin/class-wpgh-calendar-page.php';
    }

    public  function gh_calendar_shortcode()
    {

        // get calendar id  form short code

        $calendar_id = 20 ;
        ob_start();
        ?>

        <input type="hidden" name="calendar_id" id="calendar_id" value="<?php echo $calendar_id; ?>"/>
        <input  class="input" placeholder="Y-m-d" type="text" id="date" name="date" value="" autocomplete="off" required>
        Time :
        <hr/>
        <div name="times" id = "times"> </div>
        <?php
        $content = ob_get_clean();
        return $content;
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