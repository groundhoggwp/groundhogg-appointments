<?php
/**
 * The page gh_calendar
 *  create calendar page
 *
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


class WPGH_Calendar_Page
{
    /**
     * @var WPGH_Notices
     */
    public $notices;

    /**
     * @var WPGH_DB_Appointments
     */
    public $db;

    /**
     * @var WPGH_DB_Appointment_Meta
     */
    public $meta;

    /**
     * Get things started
     *
     * WPGH_Calendar_Page constructor.
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'register' ) );
        add_action( 'wp_ajax_gh_add_appointment', array($this, 'gh_add_appointment'));
        add_action( 'wp_ajax_gh_update_appointment', array($this, 'gh_update_appointment'));
        add_action( 'wp_ajax_gh_verify_code', array( WPGH_APPOINTMENTS()->google_calendar , 'gh_verify_code'));

        if ( isset( $_GET['page'] ) && $_GET[ 'page' ] === 'gh_calendar' ){
            add_action( 'init' , array( $this, 'process_action' )  );
            add_action( 'admin_enqueue_scripts' , array( $this, 'scripts' )  );
            $this->notices = WPGH()->notices;
        }
        $this->db   = WPGH_APPOINTMENTS()->appointments;
        $this->meta = WPGH_APPOINTMENTS()->appointmentmeta;
    }

    /**
     * enqueue editor scripts for full calendar
     */
    public function scripts()
    {

        //wp_enqueue_scripts('moment' ,"http://momentjs.com/downloads/moment.min.js" ,array());
        //wp_enqueue_scripts('moment-tz' ,"http://momentjs.com/downloads/moment-timezone-with-data.min.js",array());
        wp_enqueue_script(  'jstz', WPGH_APPOINTMENT_ASSETS_FOLDER . 'js/jstz.min.js', array(), filemtime( WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/js/jstz.min.js' ) );
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style(  'jquery-ui' );
        wp_enqueue_script( 'ajax-script-appointment',    WPGH_APPOINTMENT_ASSETS_FOLDER . '/js/appointments.js',    array('jquery'),     filemtime( WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/js/appointments.js' ) );
        wp_localize_script('ajax-script-appointment', 'ajax_object',array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'we_value' => 1233 ) );
        wp_enqueue_script( 'gh-calendar',    WPGH_APPOINTMENT_ASSETS_FOLDER . '/js/appointment-frontend.js',    array('jquery'),     filemtime( WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/js/appointment-frontend.js' ) );
        wp_localize_script( 'gh-calendar', 'ghAppointment', array(
            'ajax_url'          => admin_url( 'admin-ajax.php' ),
            'invalidDateMsg'    => __( 'Please select a time slot first.', 'groundhogg' ),
            'invalidDetailsMsg' => __( 'Please make sure all your details are filled out.', 'groundhogg' ),
            'invalidEmailMsg'   => __( 'Your email address is invalid.', 'groundhogg' ),
        ) );
        wp_enqueue_script( 'calender-moment',WPGH_APPOINTMENT_ASSETS_FOLDER . '/lib/fullcalendar/lib/moment.min.js', array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/lib/fullcalendar/lib/moment.min.js' ) );
        wp_enqueue_script( 'calender-main',  WPGH_APPOINTMENT_ASSETS_FOLDER . '/lib/fullcalendar/fullcalendar.js',   array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/lib/fullcalendar/fullcalendar.js') );
        wp_enqueue_style ( 'calender-css',   WPGH_APPOINTMENT_ASSETS_FOLDER . '/lib/fullcalendar/fullcalendar.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/lib/fullcalendar/fullcalendar.css') );
        wp_enqueue_style ( 'backend-css',   WPGH_APPOINTMENT_ASSETS_FOLDER . '/css/backend.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/css/backend.css') );

    }

    /**
     *  Add sub menu in Calendar in Groundhogg Plugin.
     *
     */
    public function register()
    {
        $page = add_submenu_page(
            'groundhogg',
            'Calendars',
            'Calendars',
            'view_calendar',
            'gh_calendar',
            array($this, 'page')
        );
        add_action("load-" . $page, array($this, 'help'));
    }

    /**
     * Help bar
     */
    public function help()
    {
        //todo
    }

    /**
     * Get a list of affected calendars
     *
     * @return array|bool
     */
    private function get_calendars()
    {
        $calendars = isset( $_REQUEST['calendar'] ) ? $_REQUEST['calendar'] : null;
        if ( ! $calendars )
            return false;
        return is_array( $calendars )? array_map( 'intval', $calendars ) : array( intval( $calendars ) );
    }

    /**
     * Get the current action
     *
     * @return bool
     */
    private function get_action()
    {
        if ( isset( $_REQUEST['filter_action'] ) && ! empty( $_REQUEST['filter_action'] ) )
            return false;

        if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] )
            return $_REQUEST['action'];

        if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] )
            return $_REQUEST['action2'];

        return false;
    }

    /**
     * Get the previous action
     *
     * @return mixed
     */
    private function get_previous_action()
    {
        $action = get_transient( 'gh_last_action' );
        delete_transient( 'gh_last_action' );
        return $action;
    }

    /**
     * Get the current screen title
     */
    private function get_title()
    {
        switch ( $this->get_action() ){
            case 'add':
                _e( 'Add Calendar' , 'groundhogg' );
                ?><a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=add' ); ?>"><?php _e( 'Add New' ); ?></a><?php
                break;
            case 'view_appointment':
                _e( 'Edit Appointment' , 'groundhogg' );
                break;
            case 'add_appointment':
                _e( 'Add Appointment' , 'groundhogg' );
                ?><a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=edit&calendar='.$_GET['calendar'] ); ?>"><?php _e( 'Edit Calendar' ); ?></a> <?php
                break;
            case 'view':
                _e( 'Calendar' , 'groundhogg' );
                ?><a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar='.$_GET['calendar'] ); ?>"><?php _e( 'Add New Appointment' ); ?></a><?php
                 break;
            default:
                _e( 'Calendars', 'groundhogg' );
                ?><a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=add' ); ?>"><?php _e( 'Add New' ); ?></a><?php
        }
    }

    /**
     * Process the current action
     */
    public function process_action()
    {
        if ( ! $this->get_action() || ! $this->verify_action() )
            return;

        $base_url = remove_query_arg( array( '_wpnonce', 'action' ), wp_get_referer() );

        switch ( $this->get_action() )
        {
            case 'add':
                if ( ! current_user_can( 'add_calendar' ) ){
                    wp_die( WPGH()->roles->error( 'add_calendar' ) );
                }
                if ( isset( $_POST[ 'add' ] ) ) {
                    $this->add_calendar();
                }
                break;
            case 'edit':
                if ( ! current_user_can( 'edit_calendar' ) ){
                    wp_die( WPGH()->roles->error( 'edit_calendar' ) );
                }
                if ( isset( $_POST[ 'update' ] ) ) {
                    $this->update_calendar();
                }
                break;
            case 'view_appointment':

                if ( ! current_user_can( 'edit_appointment' ) ){
                    wp_die( WPGH()->roles->error( 'edit_appointment' ) );
                }
                if ( isset( $_POST[ 'update_appointment' ] ) ) {
                    $this->update_appointment();
                }
                break;

            case 'delete':
                if ( ! current_user_can( 'delete_calendar' ) ){
                    wp_die( WPGH()->roles->error( 'delete_calendar' ) );
                }
                $this->delete_calendar();
                break;
            case 'access_code' :
                $client = WPGH_APPOINTMENTS()->google_calendar->get_basic_client();
                if( is_wp_error( $client ) ) {
                    $this->notices->add( 'CLIENT_ERROR', __('Please check your google clientId and Secret.'), 'error');
                    return;
                }
                $authUrl = $client->createAuthUrl();
                echo "<script>window.open(\"". $authUrl ."\",\"_self\");</script>" ;
                die();
                break;
            case 'approve' :

                if ( ! current_user_can( 'edit_appointment' ) ){
                    wp_die( WPGH()->roles->error( 'edit_appointment' ) );
                }
                // manage operation of appointment
                if ( isset( $_GET[ 'appointment' ] ) ) {
                    $appointment_id = intval( $_GET[ 'appointment' ] );
                    //get appointment
                    $appointment    = $this->db->get( $appointment_id );
                    if($appointment == null) {
                        $this->notices->add( 'NO_APPOINTMENT', __( "Appointment not found!", 'groundhogg' ), 'error' );
                        return;
                    }
                    //update status
                    $status =$this->db->update( $appointment_id,array( 'status' => 'approved' ) );
                    if ( ! $status ){
                        wp_die( 'Something went wrong' );
                    }
                    do_action('gh_calendar_appointment_approved', $appointment_id , 'approved' );
                    $this->notices->add( 'success', __( 'Appointment updated successfully!', 'groundhogg' ), 'success' );
                    wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar=' . $appointment->calendar_id ) );
                    die();
                }
                break;

            case 'delete_appointment':

                if ( ! current_user_can( 'edit_appointment' ) ){
                    wp_die( WPGH()->roles->error( 'edit_appointment' ) );
                }

                if ( isset( $_GET[ 'appointment' ] ) ) {
                    $appointment_id = intval( $_GET[ 'appointment' ] );
                    $appointment    = $this->db->get($appointment_id);
                    $status = $this->delete_appointment( $appointment_id );
                    if ( $status && ! is_wp_error($status)) {
                        $this->notices->add( 'SUCCESS', __( 'Appointment deleted successfully!', 'groundhogg' ), 'success' );
                    } else {
                        $this->notices->add( $status->get_error_code(), $status->get_error_message(), 'error' );
                    }
                    wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar=' . $appointment->calendar_id ) );
                    die();
                }
                break;

            case 'google_sync':

                if ( ! current_user_can( 'edit_appointment' ) ){
                    wp_die( WPGH()->roles->error( 'edit_appointment' ) );
                }
                if ( isset( $_GET[ 'calendar' ] ) ) {
                    $calendar_id = intval( $_GET[ 'calendar' ] );
                    $result  = WPGH_APPOINTMENTS()->google_calendar->sync( $calendar_id );
                    if ( $result && ! is_wp_error( $result ) ) {
                        $this->notices->add( 'SUCCESS', __( 'Appointments synced successfully!', 'groundhogg' ), 'success' );
                        wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar=' . $calendar_id) );
                    } else {
                        $this->notices->add( $result->get_error_code(), $result->get_error_message(), 'error' );
                        wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar=' . $calendar_id) );
                    }
                    die();
                }
                //$this->sync_google_calendar();
                break;

            case 'cancel':

                if ( ! current_user_can( 'edit_appointment' ) ){
                    wp_die( WPGH()->roles->error( 'edit_appointment' ) );
                }
                if ( isset( $_GET[ 'appointment' ] ) ) {
                    $appointment_id = intval( $_GET[ 'appointment' ] );
                    //get appointment
                    $appointment = $this->db->get($appointment_id);
                    if( $appointment == null ) {
                        $this->notices->add( 'NO_APPOINTMENT', __( "Appointment not found!", 'groundhogg' ), 'error' );
                        return;
                    }

                    //update status
                    $status = $this->db->update($appointment_id,array('status'=>'cancelled'));
                    if ( ! $status ){
                        wp_die( 'Something went wrong' );
                    }

                    do_action('gh_calendar_appointment_cancelled',$appointment_id , 'cancelled' );
                    $this->notices->add( 'success', __( 'Appointment updated successfully!', 'groundhogg' ), 'success' );
                    wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar=' . $appointment->calendar_id ) );
                    die();
                }
                break;
        }
        set_transient( 'gh_last_action', $this->get_action(), 30 );
        if ( $this->get_action() === 'edit' || $this->get_action() === 'view_appointment' || $this->get_action() === 'add' ){
            return true;
        }
        if ( $this->get_calendars() ){
            $base_url = add_query_arg( 'ids', urlencode( implode( ',', $this->get_calendars() ) ), $base_url );
        }
        wp_redirect( $base_url );
        die();
    }




    /**
     * Verify the current user can process the action
     *
     * @return bool
     */
    public function verify_action()
    {
        if ( ! isset( $_REQUEST['_wpnonce'] ) )
            return false;
        return wp_verify_nonce( $_REQUEST[ '_wpnonce' ] ) || wp_verify_nonce( $_REQUEST[ '_wpnonce' ], $this->get_action() )|| wp_verify_nonce( $_REQUEST[ '_wpnonce' ], 'bulk-calendars' );
    }

    /**
     * Display the table
     */
    public function table()
    {
        if ( ! class_exists( 'WPGH_Calendars_Table' ) ){
            include dirname(__FILE__) . '/class-wpgh-calendars-table.php';
        }
        $calendars_table = new WPGH_Calendars_Table();
         ?>
            <?php $calendars_table->prepare_items(); ?>
            <?php $calendars_table->display(); ?>
        <?php
    }

    /**
     * Display the scheduling page
     */
    public function add()
    {
        if ( ! current_user_can( 'add_calendar' ) ){
            wp_die( WPGH()->roles->error( 'add_calendar' ) );
        }
        include dirname(__FILE__) . '/add-calendar.php';
    }

    /**
     * Display the Edit calendar page
     */
    public function edit()
    {
        if ( ! current_user_can( 'edit_calendar' ) ){
            wp_die( WPGH()->roles->error( 'edit_calendar' ) );
        }
        include dirname( __FILE__ ) . '/edit-calendar.php';
    }

    /**
     * Display the screen content
     */
    public function page()
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php $this->get_title(); ?></h1>
            <?php $this->notices->notices(); ?>
            <hr class="wp-header-end">
            <?php switch ( $this->get_action() ){
                case 'add':
                    $this->add();
                    break;
                case 'edit':
                    $this->edit();
                    break;
                case 'view_appointment':
                    $this->view_appointment();
                    break;
                case 'add_appointment':
                    $this->add_appointment();
                    break;
                default:
                    $this->table();
            } ?>
        </div>
        <?php
    }

    /**
     * Handles Delete calendar request form list of calendar table
     */
    private function delete_calendar()
    {
        //get the ID of the calendar
        if ( isset( $_GET[ 'calendar' ] ) ){
            $calendar = intval( $_GET[ 'calendar' ] );
        }
        if ( ! isset( $calendar ) ){
            wp_die( __( "Please select a calendar to delete.", 'groundhogg' ) );
        }
        WPGH_APPOINTMENTS()->calendar->delete( $calendar );
    }

    /**
     * Handles post request of updating calendar details.
     *
     */
    private function update_calendar()
    {

        if ( ! isset( $_POST['owner_id'] ) ||  $_POST['owner_id'] == 0 ){
            $this->notices->add( 'NO_OWNER', __( "Please select a valid user.", 'groundhogg' ), 'error' );
            return;
        }
        if ( ! isset( $_POST['name'] ) || $_POST['name'] == '' ){
            $this->notices->add( 'NO_NAME', __( "Please enter name of calendar.", 'groundhogg' ), 'error' );
            return;
        }
        if ( ! isset( $_POST['calendar'] ) || $_POST['calendar'] == '' ){
            $this->notices->add( 'NO_NAME', __( "Calendar not found.", 'groundhogg' ), 'error' );
            return;
        }

        // ADD CALENDAR in DATABASE
        $calendar_id = intval( $_POST[ 'calendar' ] );
        $args =  array(
            'user_id' =>  intval( $_POST['owner_id'] ),
            'name'    => sanitize_text_field( stripslashes ( $_POST['name'] ) ),
        );
        if ( isset( $_POST['description'] ) ) {
            $args[ 'description' ]  =  sanitize_text_field( stripslashes ( $_POST['description'] ) );
        }
        //update operation
        $status  = WPGH_APPOINTMENTS()->calendar->update( $calendar_id, $args );
        if ( ! $status ){
            wp_die( 'Something went wrong' );
        }
        //update meta
        // days
        if(isset( $_POST[ 'checkbox' ] ) ){
            $checkbox = (array)$_POST['checkbox'];
            $checkbox = array_map( 'sanitize_text_field', $checkbox );
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id,'dow', $checkbox ) ;
        }

        //Update calendar list

        $google_calendar_list = (array)$_POST['google_calendar_list'];
        $google_calendar_list = array_map( 'sanitize_text_field', $google_calendar_list );
        WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id,'google_calendar_list', $google_calendar_list ) ;


        // start time
        if( isset( $_POST[ 'starttime' ] ) ) {
            if( isset( $_POST[ 'endtime' ] )) {
                $end_time  = $_POST[ 'endtime' ];
            } else {
                $end_time  = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $calendar_id,'end_time',true );
            }

            if( strtotime($end_time) < strtotime( $_POST[ 'starttime' ] ) ) {
                $this->notices->add( 'INVALID_STARTTIME', __( "End time can not be smaller then start time.", 'groundhogg' ), 'error' );
            } else {
                WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'start_time', sanitize_text_field( stripslashes ( $_POST['starttime'] ) ) );
            }
        }
        //end time
        if( isset( $_POST['endtime'] ) ) {

            if( isset( $_POST['starttime'] )) {
                $start_time  = $_POST['starttime'];
            } else {
                $start_time  = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time',true);
            }

            if( strtotime($start_time) < strtotime( $_POST['endtime'] ) ) {
                WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'end_time', sanitize_text_field( stripslashes( $_POST['endtime'] ) ) );
            } else {
                $this->notices->add( 'INVALID_STARTTIME', __( "End time can not be smaller then start time.", 'groundhogg' ), 'error' );
            }
        }
        // appointment
        $hour = intval( $_POST['slot_hour'] );
        $min  = intval( $_POST['slot_minute'] );
        // Enter time slot info
        if ( $min == 0 && $hour == 0 ) {
            $hour = 1;
            $min  = 0;
        }
        if ($hour == 0 && ( $min < 5)  ) {
            $min = 5;
        }
        // update meta
        WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'slot_hour', $hour );
        WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'slot_minute', $min);

        $buffer_time  = intval( $_POST[ 'buffer_time' ] );
        WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'buffer_time', $buffer_time );

        $busy_slot = intval( $_POST[ 'busy_slot' ] );
        WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'busy_slot', $busy_slot );

        $message = wp_kses_post( stripslashes( $_POST['message'] ) );
        if ($message != '') {
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'message', $message );
        }

        $title = sanitize_text_field( stripslashes( $_POST['slot_title'] ) );
        if ($title != '') {
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'slot_title', $title );
        }

        $main_color = sanitize_text_field( stripslashes( $_POST['main_color'] ) );
        if ( $main_color ) {
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'main_color', $main_color );
        }

        $slots_color = sanitize_text_field( stripslashes( $_POST['slots_color'] ) );
        if ( $slots_color ) {
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'slots_color', $slots_color );
        }

        $font_color = sanitize_text_field( stripslashes( $_POST[ 'font_color' ] ) );
        if ( $font_color ) {
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'font_color', $font_color );
        }

        $this->notices->add( 'success', __( 'Calendar updated successfully !', 'groundhogg' ), 'success' );
        wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar_id ) );
        die();
    }

    /**
     * Schedule a new calendar.
     * handle post request form add calendar
     */
    private function add_calendar()
    {
        // add calendar operation
        if ( ! isset( $_POST['owner_id'] ) ||  $_POST['owner_id'] == 0 ){
            $this->notices->add( 'NO_OWNER', __( "Please select a valid user.", 'groundhogg' ), 'error' );
            return;
        }
        if ( ! isset( $_POST['name'] ) || $_POST['name'] == '' ){
            $this->notices->add( 'NO_NAME', __( "Please enter name of calendar.", 'groundhogg' ), 'error' );
            return;
        }
        // ADD CALENDAR in DATABASE
        $args =  array(
            'user_id' =>  intval( $_POST['owner_id'] ),
            'name'    => sanitize_text_field( stripslashes ( $_POST['name'] ) ),
        );
        if ( isset( $_POST['description'] ) ) {
            $args[ 'description' ]  =  sanitize_text_field( stripslashes ( $_POST[ 'description' ] ) );
        }
        // ADD OPERATION
        $calendar_id = WPGH_APPOINTMENTS()->calendar->add( $args ) ;
        //META OPERATION
        if ( ! $calendar_id ){
            wp_die( __('Something went wrong' ,'groundhogg'));
        }
        // Enter metadata of calendar
        // days
        if(isset( $_POST[ 'checkbox' ] ) ){
            $checkbox = (array)$_POST['checkbox'];
            $checkbox = array_map( 'sanitize_text_field', $checkbox );
            WPGH_APPOINTMENTS()->calendarmeta->add_meta( $calendar_id,'dow', $checkbox ) ;
        }

        // start time
        if( isset( $_POST[ 'starttime' ] ) ) {
            if( isset( $_POST[ 'endtime' ] ) ) {
                $end_time  = $_POST[ 'endtime' ];
            } else {
                $end_time  = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $calendar_id,'end_time',true );
            }

            if( strtotime($end_time) < strtotime( $_POST['starttime'] ) ) {
                $this->notices->add( 'INVALID_STARTTIME', __( "End time can not be smaller then start time.", 'groundhogg' ), 'error' );
            } else {
                WPGH_APPOINTMENTS()->calendarmeta->add_meta($calendar_id, 'start_time', sanitize_text_field( stripslashes ( $_POST[ 'starttime' ] ) ) );
            }
        }
        //end time
        if( isset( $_POST['endtime'] ) ) {

            if( isset( $_POST['starttime'] )) {
                $start_time  = $_POST['starttime'];
            } else {
                $start_time  = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time',true);
            }
            if( strtotime($start_time) < strtotime( $_POST['endtime'] ) ) {
                WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'end_time', sanitize_text_field( stripslashes ( $_POST['endtime'] ) ) );
            } else {
                $this->notices->add( 'INVALID_STARTTIME', __( "End time can not be smaller then start time.", 'groundhogg' ), 'error' );
            }
        }
        $hour = intval( $_POST['slot_hour'] );
        $min  = intval( $_POST['slot_minute'] );
        // Enter time slot info
        if ( $min == 0 && $hour == 0 ) {
            $hour = 1;
            $min  = 0;
        }
        if ($hour == 0 && ( $min < 5)  ) {
            $min = 5;
        }
        // add meta
        WPGH_APPOINTMENTS()->calendarmeta->add_meta( $calendar_id, 'slot_hour', $hour  );
        WPGH_APPOINTMENTS()->calendarmeta->add_meta( $calendar_id, 'slot_minute', $min );
        // add custom message
        $message = wp_kses_post( $_POST['message'] );
        if ($message == '') {
            $message = 'Appointment booked successfully';
        }
        WPGH_APPOINTMENTS()->calendarmeta->add_meta( $calendar_id , 'message', $message );
        $title   = sanitize_text_field( stripslashes (  $_POST['slot_title'] ) );
        if ($title == '') {
            $title = 'Time Slot';
        }
        WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'slot_title', $title );

        $main_color = sanitize_text_field( stripslashes( $_POST['main_color'] ) );
        if ( $main_color ) {
            WPGH_APPOINTMENTS()->calendarmeta->add_meta( $calendar_id , 'main_color', $main_color );
        }

        $slots_color = sanitize_text_field( stripslashes( $_POST['slots_color'] ) );
        if ( $slots_color ) {
            WPGH_APPOINTMENTS()->calendarmeta->add_meta( $calendar_id , 'slots_color', $slots_color );
        }

        $font_color = sanitize_text_field( stripslashes( $_POST[ 'font_color' ] ) );
        if ( $font_color ) {
            WPGH_APPOINTMENTS()->calendarmeta->add_meta( $calendar_id , 'font_color', $font_color );
        }
        $buffer_time  = intval( $_POST[ 'buffer_time' ] );
        WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'buffer_time', $buffer_time );

        $busy_slot = intval( $_POST[ 'busy_slot' ] );
        WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'busy_slot', $busy_slot );

        $this->notices->add( 'success', __( 'New calendar added!', 'groundhogg' ), 'success' ); // not working
        wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar_id ) );
        die();
    }

    /**
     * Display the reporting page
     */
    public function view_appointment()
    {
        if ( ! current_user_can( 'view_appointment' ) ){
            wp_die( WPGH()->roles->error( 'view_appointment' ) );
        }

        include dirname(__FILE__) . '/view-appointment.php';
    }


    /**
     * Delete appointment form google calendar and Groundhogg calendar
     *
     * @param $appointment_id
     * @return bool|void
     */
    public function delete_appointment($appointment_id )
    {
        $appointment_id = intval( $appointment_id );
        //get appointment
        $appointment = $this->db->get($appointment_id);
        if(! $appointment  ) {
            new WP_Error ('NO_APPOINTMENT', __( "Appointment not found!", 'groundhogg' ) );
            return;
        }

        //DELETE appointment form the google if there are any ..
        WPGH_APPOINTMENTS()->google_calendar->delete_appointment_from_google($appointment_id);
        do_action('gh_calendar_appointment_deleted', $appointment_id , 'deleted' );
        $status = $this->db->delete($appointment_id);
        if ( ! $status ){
           new WP_Error( 'ERROR' ,__('Something went wrong! ' , 'groundhogg' ) );
        }
        return true;

    }


    /**
     *  Display add appointment page
     */
    public function add_appointment()
    {
        include dirname( __FILE__ ) . '/add-appointment.php';
    }

    /**
     * Handles post request form the view appointment to update appointment.
     */
    private  function update_appointment()
    {
        if ( ! isset( $_POST['contact_id'] ) ||  $_POST['contact_id'] == 0 ){
            $this->notices->add( 'NO_CONTACT', __( "Please select a valid contact", 'groundhogg' ), 'error' );
            return;
        }

        if ( ! isset( $_POST['appointment'] ) ||  $_POST['appointment'] == 0 ){
            $this->notices->add( 'NO_APPOINTMENT', __( "Please select a valid appointment.", 'groundhogg' ), 'error' );
            return;
        }

        if ( ! isset( $_POST['calendar'] ) ||  $_POST['calendar'] == 0 ){
            $this->notices->add( 'NO_CALENDAR', __( "Please select a valid calendar.", 'groundhogg' ), 'error' );
            return;
        }

        $contact_id      = intval( $_POST[ 'contact_id' ] );
        $appointment_id  = intval( $_POST[ 'appointment' ] );
        $calendar_id     = intval( $_POST[ 'calendar' ]);
        $start_time      = strtotime($_POST[ 'start_date' ].' '.$_POST[ 'start_time' ]);
        $end_time        = strtotime($_POST[ 'end_date' ].' '.$_POST[ 'end_time' ]);
        if ( $start_time > $end_time ) {
            //check for times
            $this->notices->add( 'INVALID_TIMES', __( "End time can not be earlier then start time.", 'groundhogg' ), 'error' );
            return;
        }

        //check for appointment clash.
        global $wpdb;
        $appointments_table_name  = WPGH_APPOINTMENTS()->appointments->table_name;
        $appointments = $wpdb->get_results( "SELECT * FROM $appointments_table_name as a WHERE a.start_time >= $start_time AND a.end_time <=  $end_time AND a.calendar_id = $calendar_id AND a.ID != $appointment_id " );
        if ( count( $appointments ) > 0 ) {
            $this->notices->add( 'APPOINTMENT_CLASH', __( "You already have appointment in this time slot.", 'groundhogg' ), 'error' );
            return;
        }

        $all_appoinments  = $this->db->get_appointments();
        foreach ( $all_appoinments as $appo ) {
            if( ( ( $start_time >= $appo->start_time && $start_time < $appo->end_time ) || ( $end_time >= $appo->start_time && $end_time < $appo->end_time ) ) && $appo->ID != $appointment_id  ) {
                $this->notices->add( 'APPOINTMENT_CLASH', __( "You already have appointment in this time slot.", 'groundhogg' ), 'error' );
                return;
            }
        }

        // update query
        $status     = $this->db->update($appointment_id ,  array(
            'contact_id'    => $contact_id,
            'name'          => sanitize_text_field( stripslashes( $_POST[ 'appointmentname' ] ) ),
            'start_time'    => $start_time,
            'end_time'      => $end_time
        ));
        //update notes
        if ( isset( $_POST['description'] ) ) {

            $this->meta->update_meta($appointment_id , 'note', sanitize_text_field(stripslashes( $_POST[ 'description' ] ) ) );
        }

        if( $status ) {
            //update google calendar  ..
            $appointment    =  $this->db->get_appointment( $appointment_id );
            $access_token   = WPGH_APPOINTMENTS()->calendarmeta->get_meta($appointment->calendar_id , 'access_token',true) ;
            $google_calendar_id = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $appointment->calendar_id ,'google_calendar_id' ,true ) ;
            if ( $access_token && $google_calendar_id) {
                $client     = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($appointment->calendar_id);
                $service    = new Google_Service_Calendar($client);
                if ( WPGH_APPOINTMENTS()->google_calendar->is_valid_calendar( $calendar_id ,$google_calendar_id ,$service )) {
                    $contact = WPGH()->contacts->get($appointment->contact_id);
                    $event = new Google_Service_Calendar_Event(array(
                        'id' => 'ghcalendarcid' . $appointment->calendar_id . 'aid' . $appointment->ID,
                        'summary' => $appointment->name,
                        'description' => $this->meta->get_meta($appointment->ID, 'note', true),
                        'start' => WPGH_APPOINTMENTS()->google_calendar->get_google_time($appointment->start_time),
                        'end' => WPGH_APPOINTMENTS()->google_calendar->get_google_time( $appointment->end_time ),
                        'attendees' => array(
                            array('email' => $contact->email),
                        ),
                    ));
                    $updatedEvent = $service->events->update($google_calendar_id, 'ghcalendarcid' . $appointment->calendar_id . 'aid' . $appointment_id, $event);
                }
            }
            $this->notices->add( 'success', __( 'Appointment updated successfully !' , 'groundhogg' ), 'success' );
        } else {
            $this->notices->add( 'UPDATE_FAILED', __( "Something went wrong while update.", 'groundhogg' ), 'error' );
        }
        return;
    }


    /**
     * AJAX call to update appointments from admin add appointment section
     *
     * Requested By AJAX
     */
    public function gh_update_appointment()
    {

        if ( ! current_user_can( 'edit_appointment' ) ){
            $response = array(   'status' => 'failed','msg' => __( 'Your user role does not have the required permissions to Edit appointment.' ,'groundhogg'));
            wp_die( json_encode($response) );
        }

        // Handle update appointment
        $appointment_id  = intval( $_POST['id'] );
        $start_time      = strtotime( sanitize_text_field( stripslashes( $_POST['start_time'] ) ) );
        $end_time        = strtotime( sanitize_text_field( stripslashes( $_POST['end_time'] ) ) );

        // update appointment detail
        $status = $this->db->update($appointment_id ,array(
            'start_time'    => $start_time,
            'end_time'      => $end_time,
        ));

        if ($status){
            do_action('gh_calendar_update_appointment_admin',$appointment_id , 'reschedule_admin' );
            //update appointment on google

            // retrieve appointment and update its detail on google calendar.
            $appointment  =  $this->db->get_appointment( $appointment_id );
            $access_token  = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $appointment->calendar_id , 'access_token',true );
            $google_calendar_id = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $appointment->calendar_id ,'google_calendar_id' ,true );
            if ( $access_token  && $google_calendar_id ) {
                $client     = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($appointment->calendar_id);
                $service    = new Google_Service_Calendar($client);
                if ( WPGH_APPOINTMENTS()->google_calendar->is_valid_calendar( $appointment->calendar_id ,$google_calendar_id ,$service )) {
                    $event = new Google_Service_Calendar_Event(array(
                        'start' => WPGH_APPOINTMENTS()->google_calendar->get_google_time($appointment->start_time),
                        'end' => WPGH_APPOINTMENTS()->google_calendar->get_google_time( $appointment->end_time ),
                    ));
                    $updatedEvent = $service->events->patch($google_calendar_id, 'ghcalendarcid' . $appointment->calendar_id . 'aid' . $appointment_id, $event);
                }
            }
            wp_die( json_encode( array(
                'status' => 'success',
                'msg'    => __('Appointment reschedule successfully.','groundhogg')
            )));
        } else {
            wp_die( json_encode( array(
                'status' => 'failed',
                'msg'    => __('Something went wrong !','groundhogg')
            )));
        }
    }

    /**
     * AJAX  call to add appointments from admin section
     *
     * Requested by AJAX
     */
    public function gh_add_appointment()
    {

        if ( ! current_user_can( 'add_appointment' ) ){
            $response = array( 'msg' => __('Your user role does not have the required permissions to add appointment.','groundhogg') );
            wp_die( json_encode($response) );
        }

        // ADD APPOINTMENTS using AJAX.
        $start              = intval( $_POST[ 'start_time' ] );
        $end                = intval( $_POST[ 'end_time' ]  );
        if ( ! $start || ! $end ){
            $response = array( 'status' => 'failed' , 'msg' => __('PLease provide a valid date selection.' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }


        $contact_id         = intval( $_POST['id'] );
        $note               = sanitize_text_field( stripslashes ( $_POST['note'] ) );
        $appointment_name   = sanitize_text_field( stripslashes ( $_POST [ 'appointment_name'] ) ) ;
        $calendar_id        = sanitize_text_field( stripslashes ($_POST [ 'calendar_id'] ) );
        $start              = strtotime( '+10 seconds',$start);
        //end minus buffer time
        $buffer_time    = intval( WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'buffer_time',true) );
        $end  =  strtotime( "- $buffer_time minute", $end );

        // perform insert operation
        $appointment_id  = $this->db->add( array (
            'contact_id'    => $contact_id,
            'calendar_id'   => $calendar_id,
            'name'          => $appointment_name,
            'status'        => 'pending',
            'start_time'    => $start,    //strtotime()
            'end_time'      => ($end)       //strtotime()
        ));

        // Insert meta
        if ( $appointment_id === false ){
            $response = array( 'msg' => __('Something went wrong. Appointment not created !' ,'groundhogg'));
            wp_die( json_encode( $response ) );
        }

        if ( $note != ''){
            WPGH_APPOINTMENTS()->appointmentmeta->add_meta($appointment_id , 'note' , $note);
        }

        // generate array to create event for full calendar to display
        $response = array(
            'msg' =>'Appointment booked successfully.',
            'appointment' => array(
                'id'         => $appointment_id,
                'title'      => $appointment_name,
                'start'      => $start*1000,//$start,
                'end'        => $end * 1000,//$end,
                'constraint' => 'businessHours',
                'editable'   => true,
                'allDay'     => false,
                'color'      => '#0073aa',
                'url'        => admin_url( 'admin.php?page=gh_calendar&action=view_appointment&appointment=' . $appointment_id ),// link to view appointment page
            )
        );

        do_action('gh_calendar_add_appointment_admin',$appointment_id , 'create_admin' );

        //add appointment inside google calendar //
        $access_token  = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id , 'access_token',true) ;
        $google_calendar_id = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $calendar_id ,'google_calendar_id' ,true ) ;
        if ( $access_token && $google_calendar_id ) {
            $client = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($calendar_id);
            $service = new Google_Service_Calendar($client);
            if ( WPGH_APPOINTMENTS()->google_calendar->is_valid_calendar( $calendar_id ,$google_calendar_id ,$service )) {
                $contact = WPGH()->contacts->get($contact_id);
                $event = new Google_Service_Calendar_Event(array(
                    'id' => 'ghcalendarcid' . $calendar_id . 'aid' . $appointment_id,
                    'summary' => $appointment_name,
                    'description' => $note,
                    'start' => WPGH_APPOINTMENTS()->google_calendar->get_google_time($start),
                    'end' => WPGH_APPOINTMENTS()->google_calendar->get_google_time( $end ),
                    'attendees' => array(
                        array('email' => $contact->email),
                    ),
                ));
                $event = $service->events->insert($google_calendar_id, $event);
            }
        }
        wp_die( json_encode( $response ) );
    }

}