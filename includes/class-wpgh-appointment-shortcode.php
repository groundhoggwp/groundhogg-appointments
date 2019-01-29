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
        add_action( 'wp_ajax_nopriv_gh_add_appointment_client', array( $this , 'gh_add_appointment_client' ) );
        add_action( 'wp_ajax_gh_get_appointment_client', array( $this , 'gh_get_appointment_client' ) );
        add_action( 'wp_ajax_nopriv_gh_get_appointment_client', array( $this , 'gh_get_appointment_client' ) );
        add_shortcode( 'gh_calendar', array( $this , 'gh_calendar_shortcode' ) ) ;
    }

    /**
     * Load scripts for  operations
     */
    public function load_scripts() {
        wp_enqueue_script(  'gh-calendar', WPGH_APPOINTMENT_ASSETS_FOLDER . 'js/appointment-frontend.js', array('jquery', 'jquery-ui-datepicker' ), filemtime( WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/js/appointment-frontend.js' ) );
        wp_localize_script( 'gh-calendar', 'ghAppointment', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'invalidDateMsg'    => __( 'Please select a time slot first.', 'groundhogg' ),
            'invalidDetailsMsg' => __( 'Please make sure all your details are filled out.', 'groundhogg' ),
            'invalidEmailMsg'   => __( 'Your email address is invalid.', 'groundhogg' ),
        ) );
    }

    /**
     * Clean all the selected calendar slots from google calendars.
     *
     * @param $calendar_id
     * @param $google_min
     * @param $google_max
     * @param $final_slots
     * @return array -  returns array of final slots after cleaning them
     */
    public function clean_google_slots ( $calendar_id ,$google_min ,$google_max , $final_slots )
    {
        $google_appointments =  null;
        $client = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($calendar_id);
        $service = new Google_Service_Calendar($client);
        $google_calendar_list = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $calendar_id , 'google_calendar_list',true) ;
        if (count( $google_calendar_list ) > 0) {
            foreach ( $google_calendar_list as $google_cal)
            {
                try
                {
                    $google_calendar = $service->calendars->get( $google_cal);
                    $optParams = array(
                        'orderBy' => 'startTime',
                        'singleEvents' => true,
                        'timeMin' => $google_min,
                        'timeMax' => $google_max
                    );
                    $results = $service->events->listEvents($google_calendar->getId(), $optParams);
                    $events = $results->getItems();

                    if ( ! empty($events)) {
                        foreach ($events as $event) {
                            //echo $event->getId() . ' - ' . ' - ' . strtotime(date($event->start->dateTime)) . ' - ' . $event->start->dateTime . ' - ' . $event->getDescription() . ' -e- ' . strtotime(date($event->end->dateTime)) . ' - ' . $event->getSummary();
                            $google_start   = $event->start->dateTime;
                            $google_end     = $event->end->dateTime;
                            if ( ! empty($google_start) && ! empty($google_end)) {

                                //  wp_die( $date('c' , strtotime(date($event->start->dateTime))));
                                $google_appointments[] =  array(
                                    'name'       => $event->getSummary(),
                                    'start_time' => (strtotime(date($event->start->dateTime))),
                                    'end_time'   => (strtotime(date($event->end->dateTime)))
                                );
                            }
                        }
                    }

                } catch ( Exception $e)
                {

                    // catch if the calendar does not exist in google calendar
                }
            }
        }

        if (count( $google_appointments ) > 0 ) {
            $google_all_slots = $final_slots;
            $google_available_slots = null;

            // first clean
            foreach ($google_all_slots as $slot) {
                $slotbooked = false;
                foreach ($google_appointments as $appointment) {
                    //if ( ( ( $slot['start'] >= $appointment->start_time && $slot['start'] < $appointment->end_time ) || ( $slot['end'] >= $appointment->start_time && $slot['end'] < $appointment->end_time ) )  ) {
                    if ( ( ( $slot['start'] >= $appointment['start_time'] && $slot['start'] < $appointment['end_time'] ) || ( $slot['end'] >= $appointment['start_time'] && $slot['end'] < $appointment['end_time'] ) )  ) {

                        $slotbooked = true;
                        break;
                    }
                }
                if (!$slotbooked) {
                    $google_available_slots[] = $slot;
                }
            }

            //second clean
            $google_final_slots = null;
            // cleaning where appointments are smaller then slots
            foreach( $google_available_slots as $slot){
                $slotbooked = false;
                foreach ($google_appointments as $appointment) {
                    if ( ($appointment['start_time'] >= $slot['start'] && $appointment['start_time'] < $slot['end'])) {
                        $slotbooked = true;
                        break;
                    }
                }
                if (!$slotbooked) {
                    $google_final_slots[] = $slot;
                }
            }
            $final_slots = $google_final_slots;
            return $final_slots;
        } else {
            return $final_slots;
        }
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

        $email      = sanitize_email( stripslashes( $_POST[ 'email' ] ) );
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ){
            $response = array( 'status' => 'failed' , 'msg' => __('Please enter a valid email address.' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }

        $first_name         = sanitize_text_field( stripslashes( $_POST[ 'first_name' ] ) );
        $last_name          = sanitize_text_field( stripslashes( $_POST[ 'last_name' ] ) );
        $appointment_name   = sanitize_text_field( stripslashes( $_POST [ 'appointment_name'] ) );
        $calendar_id        = sanitize_text_field( stripslashes( $_POST [ 'calendar_id'] ) );

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

        //ADD Appointment into google calendar
        $appointment        = WPGH_APPOINTMENTS()->appointments->get($appointment_id);
        $access_token       = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $appointment->calendar_id , 'access_token',true) ;
        $google_calendar_id = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $appointment->calendar_id ,'google_calendar_id' ,true );
        if ( $access_token && $google_calendar_id ) {
            $client = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($calendar_id);
            $service = new Google_Service_Calendar($client);
            if ( WPGH_APPOINTMENTS()->google_calendar->is_valid_calendar( $appointment->calendar_id  ,$google_calendar_id ,$service )) {
                $contact = WPGH()->contacts->get($appointment->contact_id);
                $event = new Google_Service_Calendar_Event(array(
                    'id' => 'ghcalendarcid' . $appointment->calendar_id . 'aid' . $appointment->ID,
                    'summary' => $appointment->name,

                    'start' => array(
                        'dateTime' => date('Y-m-d\TH:i:s', $appointment->start_time),
                        'timeZone' => get_option('timezone_string'),
                    ),
                    'end' => array(
                        'dateTime' => date('Y-m-d\TH:i:s', $appointment->end_time),
                        'timeZone' => get_option('timezone_string'),
                    ),
                    'attendees' => array(
                        array('email' => $contact->email),
                    ),
                ));
                $event = $service->events->insert( $google_calendar_id, $event );
            }
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

        $date           = sanitize_text_field( stripslashes( $_POST['date'] ) );
        $calendar_id    = intval( stripslashes( $_POST['calendar'] ) );
        //get start time and end time from business hours  of a day
        $time           = current_time('timestamp' );
        $dow            = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'dow',true);
        $start_time     = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time',true);
        $end_time       = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'end_time',true);
        $slot_hour      = intval( WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_hour',true) );
        $slot_minute    = intval( WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slot_minute',true) );
        $buffer_time    = intval( WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'buffer_time',true) );
        $busy_slot      = intval( WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'busy_slot',true) );

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

        $appointments = null;
        //GET AVAILABLE TIME IN DAY
        $end_time = strtotime( $date .' '.$end_time );

        $google_min = date('c' , wpgh_convert_to_utc_0( $start_time ) );
        $google_max = date('c' , wpgh_convert_to_utc_0( $end_time ) );

        // get appointments
        //$appointments_table_name  = WPGH_APPOINTMENTS()->appointments->table_name;
        //$appointments = $wpdb->get_results( "SELECT * FROM $appointments_table_name as a WHERE a.start_time >= $start_time AND a.end_time <=  $end_time AND a.calendar_id = $calendar_id" );
        $appointments =  WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array( 'calendar_id' => $calendar_id ) );

        // generate array to populate time slots
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
            $start_time = strtotime( "+$buffer_time minute", $temp_endtime );
        }

        // remove booked appointment form the array
        // cleaning where appointment are bigger then slots
        $available_slots = null;
        foreach ($all_slots as $slot) {
            $slotbooked = false;
            foreach ($appointments as $appointment) {
                if ( ( ( $slot['start'] >= $appointment->start_time && $slot['start'] < $appointment->end_time ) || ( $slot['end'] >= $appointment->start_time && $slot['end'] < $appointment->end_time ) )  ) {
                //if ( ( ( $slot['start'] >= $appointment['start_time'] && $slot['start'] < $appointment['end_time'] ) || ( $slot['end'] >= $appointment['start_time'] && $slot['end'] < $appointment['end_time'] ) )  ) {

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


        $access_token = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $calendar_id , 'access_token',true) ;
        if ( $access_token ) {
            $final_slots = $this->clean_google_slots($calendar_id,$google_min,$google_max,$final_slots);
        }

        if ( $final_slots == null ) {
            $response = array(  'status'=>'failed', 'msg' => __('No appointments available.' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }

        // operation on data _ MAKE ME LOOK BUSY - on final_slots
         $display_slot = null ;
        if ( $busy_slot  ===  0 || $busy_slot >= count( $final_slots ) || current_user_can( 'edit_appointment' ) ) {
            $display_slot = $final_slots;
        } else {
            $this->my_shuffle($final_slots ,  strtotime( $date ) );
            for ($i=0; $i < $busy_slot  ;$i++)
            {
                $display_slot[] = $final_slots[$i];
            }
            //short array
            $sort = array();
            foreach ($display_slot as $key => $row)
            {
                $sort[$key] = $row['start'];
            }
            array_multisort($sort, SORT_ASC, $display_slot );
        }
        $response = array(  'status'=> 'success', 'slots' => $display_slot );
        wp_die( json_encode( $response ) );
    }

    /**
     * shuffle array to get random appointment from appointment list.
     * @param $items
     * @param $seed
     */
    public function my_shuffle(&$items, $seed)
    {
        @mt_srand($seed);
        for ($i = count($items) - 1; $i > 0; $i--)
        {
            $j = @mt_rand(0, $i);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }
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
        wp_enqueue_style ( 'jquery-ui', WPGH_APPOINTMENT_ASSETS_FOLDER . 'css/jquery-ui.min.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/css/jquery-ui.min.css') );
        wp_enqueue_style ( 'jquery-ui-calendar', WPGH_APPOINTMENT_ASSETS_FOLDER . 'css/calendar.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/css/calendar.css') );
        wp_enqueue_style( 'wpgh-frontend', WPGH_ASSETS_FOLDER . 'css/frontend.css', array(), filemtime( WPGH_PLUGIN_DIR . 'assets/css/frontend.css' ) );
        wp_enqueue_style ( 'calender-css',   WPGH_APPOINTMENT_ASSETS_FOLDER . 'css/frontend.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/css/frontend.css') );

        wp_enqueue_script(  'jquery' );
        wp_enqueue_script(  'jquery-ui-datepicker' );


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
        $appointment_name = sanitize_text_field( stripslashes ( $args[ 'appointment_name' ] ) ); // get name for clients

        $main_color = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'main_color', true);
        if ( ! $main_color ){
            $main_color = '#f7f7f7';
        }

        $font_color = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'font_color', true);
        if ( ! $font_color ){
            $font_color = '#292929';
        }

        $slots_color = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'slots_color', true);
        if( ! $slots_color ) {
            $slots_color = '#29a2d9';
        }

        ob_start();
        ?>
        <style>
            .calendar-form-wrapper .wpgh-calendar .ui-widget{background-color: <?php echo $main_color?> ;}
            .calendar-form-wrapper .wpgh-calendar .ui-datepicker-header{border-bottom-color: <?php echo $this->adjust_brightness( $main_color, -20 )?>;}
            .calendar-form-wrapper .wpgh-calendar .ui-datepicker .ui-datepicker-title{color: <?php echo  $font_color ;?>; }
            .calendar-form-wrapper .wpgh-calendar .ui-datepicker th{color: <?php echo $font_color ;?>;background: <?php echo $this->adjust_brightness( $main_color, -20 )?>;}
            .calendar-form-wrapper .wpgh-calendar td .ui-state-default{color: <?php echo $this->adjust_brightness( $main_color, -150 )?>; color: <?php echo $font_color; ?>; }
            .calendar-form-wrapper .wpgh-calendar td .ui-state-hover, .calendar-form-wrapper .wpgh-calendar td .ui-state-active{background-color: <?php echo $this->adjust_brightness( $main_color, -20 )?>;}
            .calendar-form-wrapper .select-time .appointment-time {background-color: <?php  echo $main_color;?>;  color: <?php echo $font_color; ?>;  background: <?php echo $this->adjust_brightness( $main_color, -20 );?>;}
            .calendar-form-wrapper .select-time .appointment-time.selected {background-color: <?php  echo $slots_color;?>;  }
            .calendar-form-wrapper .select-time .appointment-time:hover{background-color: <?php  echo $slots_color;?>; }
        </style>
        <div class="calendar-form-wrapper">
            <form class="gh-calendar-form" method="post">
                <input type="hidden" name="calendar_id" id = "calendar_id" value="<?php echo $calendar_id; ?>"/>
                <input type="hidden"  id="appointment_name"  value="<?php echo $appointment_name; ?>"/>
<!--                <input type="hidden" name="hidden_data" id="hidden_data" data-start_date="" data-end_date="" data-control_id="">-->
                <div class="wpgh-calendar">
                    <div id="appt-calendar" style="width: 100%"></div>
                </div>
                <div style="text-align: center;margin-top: 15px;" id="spinner">
                    <span class="spinner" style="float: none; visibility: visible"></span>
                </div>
                <div id="time-slots" class="select-time hidden">
                    <p class="time-slot-select-text"><b><?php _e( $title , 'groundhogg' ) ?></b></p>
                    <hr class="time-slot-divider"/>

                    <div id="select_time"></div>
                    <hr class="time-slot-divider"/>
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

        <?php
        $content = ob_get_clean();
        return $content;
    }

    /**
     * Adjust shades of colour for front end calendar
     *
     * @param $hex
     * @param $steps
     * @return string
     */
    private function adjust_brightness($hex, $steps) {

    // Steps should be between -255 and 255. Negative = darker, positive = lighter
    $steps = max(-255, min(255, $steps));

    // Normalize into a six character long hex string
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
    }

    // Split into three parts: R, G and B
    $color_parts = str_split($hex, 2);
    $return = '#';

    foreach ($color_parts as $color) {
        $color   = hexdec($color); // Convert to decimal
        $color   = max(0, min(255,$color + $steps ) ); // Adjust color
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT); // Make two char hex code
    }

    return $return;
}


}