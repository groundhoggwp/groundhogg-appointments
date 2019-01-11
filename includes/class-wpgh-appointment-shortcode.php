<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPGH_Appointment_Shortcode
{
    /**
     * create short code and handles ajax requests
     *
     * WPGH_Appointment_Shortcode constructor.
     */
    public function __construct()
    {
        add_action( 'wp_enqueue_scripts', array( $this , 'load_scripts') );
        add_action( 'wp_ajax_gh_add_appointment_client', array( $this , 'gh_add_appointment_client' ) );
        add_action( 'wp_ajax_gh_get_appointment_client', array( $this , 'gh_get_appointment_client'));
        add_shortcode( 'gh_calendar', array( $this , 'gh_calendar_shortcode' ) ) ;
    }

    /**
     * Load scripts for  operations
     */
    public function load_scripts() {
        wp_enqueue_script(  'jquery' );
        wp_enqueue_style ( 'jquery-ui', WPGH_APPOINTMENT_ASSETS_FOLDER . 'css/jquery-ui.min.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/css/jquery-ui.min.css') );
        wp_enqueue_style ( 'jquery-ui-calendar', WPGH_APPOINTMENT_ASSETS_FOLDER . 'css/calendar.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/css/calendar.css') );
        wp_enqueue_script(  'jquery-ui-datepicker' );
        wp_enqueue_style ( 'calender-css',   WPGH_APPOINTMENT_ASSETS_FOLDER . 'css/frontend.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/css/frontend.css') );
        wp_enqueue_style( 'wpgh-frontend', WPGH_ASSETS_FOLDER . 'css/frontend.css', array(), filemtime( WPGH_PLUGIN_DIR . 'assets/css/frontend.css' ) );
        wp_enqueue_script(  'gh-calendar', WPGH_APPOINTMENT_ASSETS_FOLDER . '/js/appointment-frontend.js', array('jquery') , filemtime( WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/js/appointment-frontend.js' ) );
        wp_localize_script( 'gh-calendar', 'ghAppointment', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'invalidDateMsg'    => __( 'Please select a time slot first.', 'groundhogg' ),
            'invalidDetailsMsg' => __( 'Please make sure all your details are filled out.', 'groundhogg' ),
            'invalidEmailMsg'   => __( 'Your email address is invalid.', 'groundhogg' ),
        ) );
    }


    /**
     *  Handle AJAX request to add appointment inside database
     *
     *  Requested by AJAX
     */
    public function gh_add_appointment_client()
    {

        // ADD APPOINTMENTS using AJAX.
        $start      = intval( $_POST['start_time'] );
        $end        = intval( $_POST['end_time'] );

        if ( ! $start || ! $end ){
            $response = array( 'status' => 'failed' , 'msg' => __('PLease provide a valid date selection.' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }

        $email      = sanitize_email($_POST[ 'email' ]);
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ){
            $response = array( 'status' => 'failed' , 'msg' => __('Please enter a valid email address.' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }

        $first_name         = sanitize_text_field($_POST[ 'first_name' ]);
        $last_name          = sanitize_text_field($_POST[ 'last_name' ]);
        $appointment_name   = sanitize_text_field( $_POST [ 'appointment_name'] );
        $calendar_id        = sanitize_text_field( $_POST [ 'calendar_id'] );

        $contact_id = 0;
        // get contact id form email -> if contact is not found generate contact
        // check for contact

        $contact = WPGH()->contacts->get_contacts( array( 'email' => $email ) );
        if ( count($contact )  > 0 ){
            // create new contact if contact not found
            $contact_id = $contact[0]->ID;
        } else {
            $contact_id = WPGH()->contacts->add(array(
                'email' =>$email ,
                'first_name' => $first_name,
                'last_name'  => $last_name
            ));
        }
        // perform insert operation
        $appointment_id  = WPGH_APPOINTMENTS()->appointments->add( array (
            'contact_id'    => $contact_id,
            'calendar_id'   => $calendar_id,
            'name'          => $appointment_name,
            'status'        => 'pending',
            'start_time'    =>  strtotime( '+1 minute',$start),
            'end_time'      => $end
        ));
        // Insert meta
        if ( $appointment_id === false ){
            $response = array( 'status' => 'failed' , 'msg' => __('Something went wrong. Appointment not created!' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }
        // generate array for event
        //todo make dynamic (client chooses Msg!)
        $message = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id , 'message',true );
        if ($message == '' ){
            $message = 'Appointment booked successfully';
        }
        $response = array( 'status' => 'success','successMsg' => __($message,'groundhogg') );
        do_action('gh_calendar_add_appointment_client',$appointment_id , 'create_client' );
        wp_die( json_encode( $response ) );
    }

    /**
     *  GET available Appointment form the database based on calendar id.
     *
     *  Requested by AJAX
     */
    public function gh_get_appointment_client()
    {

        global $wpdb;

        $date           = sanitize_text_field(stripslashes( $_POST['date'] ) );
        $calendar_id    = intval( $_POST['calendar'] );
        //get start time and end time from business hours  of a day
        $time           = current_time('timestamp' );
        $dow            = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'dow',true);
        $start_time     = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time',true);
        $end_time       = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'end_time',true);
        $slot_hour      = intval( WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_hour',true) );
        $slot_minute    = intval( WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_minute',true) );
        $entered_dow    = date ("N", strtotime($date) );
        if ( $entered_dow == 7 ){
            $entered_dow = 0 ;
        }
        if ( in_array( $entered_dow,$dow) === false ) {
            $response = array(  'status'=>'failed', 'msg' => __( 'Sorry, no time slots are available for this date period.','groundhogg'));
            wp_die( json_encode( $response ) );
        }
        $start_time = strtotime( $date .' '.$start_time );
        // check if current time is past time or not !
        if ($start_time < $time) {
            $d = date('H:00' , $time);
            $start_time = strtotime( $d );
        }
        //GET AVAILABLE TIME IN DAY
        $end_time = strtotime( $date .' '.$end_time );
        // get appointments
        //$appointments_table_name  = WPGH_APPOINTMENTS()->appointments->table_name;
        //$appointments = $wpdb->get_results( "SELECT * FROM $appointments_table_name as a WHERE a.start_time >= $start_time AND a.end_time <=  $end_time AND a.calendar_id = $calendar_id" );
        $appointments = WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array( 'calendar_id' => $calendar_id ) );
        // generate array to populate ddl
        $all_slots = null;
        while ($start_time < $end_time)
        {
            $temp_endtime = strtotime( "+$slot_hour hour +$slot_minute minutes",$start_time);
            if ($temp_endtime <= $end_time) {
                $all_slots[] = array(
                    'start'    => $start_time,
                    'end'      => $temp_endtime,
                    'name'     => date('H:i', $start_time ).' - '.date('H:i', $temp_endtime ),
                );
            }
            $start_time = $temp_endtime;
        }
        // remove booked appointment form the array

        // cleaning where appointment are bigger then slots
        $available_slots = null;
        foreach ($all_slots as $slot) {
            $slotbooked = false;
            foreach ($appointments as $appointment) {
                //if ( ( $appointment->start_time >= $slot['start'] && $appointment->start_time < $slot['end'] ) || ($appointment->end_time >= $slot['start'] && $appointment->end_time < $slot['end']) ) {
                if ( ( ( $slot['start'] >= $appointment->start_time && $slot['start'] < $appointment->end_time ) || ( $slot['end'] >= $appointment->start_time && $slot['end'] < $appointment->end_time ) )  ) {
                    $slotbooked = true;
                    break;
                }
            }
            if (!$slotbooked) {
                $available_slots[] = $slot;
            }
        }
        $final_slots = null;
        // cleaning where appointments are smaller then slots
        foreach( $available_slots as $slot){
            $slotbooked = false;
            foreach ($appointments as $appointment) {
                if ( ($appointment->start_time >= $slot['start'] && $appointment->start_time < $slot['end'])) {
                    $slotbooked = true;
                    break;
                }
            }
            if (!$slotbooked) {
                $final_slots[] = $slot;
            }
        }

        if ( $available_slots == null ) {
            $response = array(  'status'=>'failed', 'msg' => __('No appointments available.' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }

        if ( $final_slots == null ) {
            $response = array(  'status'=>'failed', 'msg' => __('No appointments available.' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }
        // operation on data
        $response = array(  'status'=> 'success', 'slots' => $final_slots );
        wp_die( json_encode( $response ) );
    }

    /**
     * Main shortcode function
     * Accepts shortcode attributes and returns a string of HTML
     *
     * @param $atts array shortcode attributes
     * @return string
     */
    public  function gh_calendar_shortcode( $atts )
    {
        $args =  shortcode_atts(array(
            'calendar_id' => 0,
            'appointment_name' => __( 'New Client Appointment', 'groundhogg' )
        ),$atts);

        // get calendar id  form short code
        $calendar_id = intval($args['calendar_id']) ;
        //fetch calendar
        $exist = WPGH_APPOINTMENTS()->calendar->exists($calendar_id);
        if ( ! $exist ) {
            return sprintf( '<p>%s</p>', __( 'The given calendar ID does not exist.', 'groundhogg' ) );
        }
        $title = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_title', true);
        if( $title === null ) {
            $title = __( 'Time Slot', 'groundhogg' );
        }
        $appointment_name = sanitize_text_field( $args[ 'appointment_name' ] ); // get name for clients
        ob_start();
        ?>
        <div class="calendar-form-wrapper">
            <form class="gh-calendar-form" method="post">
                <input type="hidden" name="calendar_id" id = "calendar_id" value="<?php echo $calendar_id; ?>"/>
                <input type="hidden"  id="appointment_name"  value="<?php echo $appointment_name; ?>"/>
<!--                <input type="hidden" name="hidden_data" id="hidden_data" data-start_date="" data-end_date="" data-control_id="">-->
                <div class="ll-skin-nigran">
                    <div id="appt-calendar" style="width: 100%"></div>
                </div>
                <div id="time-slots" class="select-time hidden">
                    <p class="time-slot-select-text"><b><?php _e( $title , 'groundhogg' ) ?></b></p>
                    <hr class="time-slot-divider"/>
                    <div id="select_time"></div>
                </div>
                <div id="appointment-errors" class="appointment-errors hidden"></div>
                <div id="details-form" class="details-form hidden gh-form-wrapper">
                    <div class="gh-form">
                        <div class="gh-form-row clearfix">
                            <div class="gh-form-column col-1-of-2">
                                <div class="gh-form-field">
                                    <input type="text" name="first_name" id="first_name" placeholder="First Name" required/>
                                </div>
                            </div>
                            <div class="gh-form-column col-1-of-2">
                                <div class="gh-form-field">
                                    <input type="text" name="last_name" id="last_name" placeholder="Last Name" required/>
                                </div>
                            </div>
                        </div>
                        <div class="gh-form-row clearfix">
                            <div class="gh-form-column col-1-of-1">
                                <div class="gh-form-field">
                                    <input type="email" name="email" id="email" placeholder="Email" required/>
                                </div>
                            </div>
                        </div>
                        <div class="gh-form-row clearfix">
                            <div class="gh-form-column col-1-of-1">
                                <div class="gh-form-field">
                                    <input type="submit" name="book_appointment" id="book_appointment" value="Book Appointment"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div style="text-align: center;" id="spinner">
            <span class="spinner" style="float: none; visibility: visible"></span>
        </div>
        <?php
        $content = ob_get_clean();
        return $content;
    }

}