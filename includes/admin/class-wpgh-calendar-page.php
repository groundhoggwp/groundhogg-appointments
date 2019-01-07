<?php
/**
 * The page gh_calendar
 *  create calendar page
 *
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
     * WPGH_Calendar_Page constructor.
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'register' ) );
        add_action( 'wp_ajax_gh_add_appointment', array($this, 'gh_add_appointment'));
        add_action( 'wp_ajax_gh_update_appointment', array($this, 'gh_update_appointment'));

        if ( isset( $_GET['page'] ) && $_GET[ 'page' ] === 'gh_calendar' ){
            add_action( 'init' , array( $this, 'process_action' )  );
            add_action( 'admin_enqueue_scripts' , array( $this, 'scripts' )  );
            $this->notices = WPGH()->notices;
        }

        $this->db   = WPGH_APPOINTMENTS()->appointments;
        $this->meta = WPGH_APPOINTMENTS()->appointmentmeta;
    }

    /**
     * AJAX call to update appointments from admin add appointment section
     */
    public function gh_update_appointment()
    {
        if ( ! current_user_can( 'edit_appointment' ) ){
            $response = array(   'status' => 'failed','msg' => 'Your user role does not have the required permissions to Edit appointment.' );
            wp_die( json_encode($response) );
        }
        // Handle update appointment
        $appointment_id  = $_POST['id'];
        $start_time      = strtotime( $_POST['start_time'] );
        $end_time        = strtotime( $_POST['end_time'] );

        // update appointment detail
        $status = $this->db->update($appointment_id ,array(
            'start_time'    => $start_time,
            'end_time'      => $end_time,
        ));

        if ($status){
            do_action('gh_calendar_update_appointment_admin',$appointment_id , 'reschedule_admin' );
            wp_die( json_encode( array(
                'status' => 'success',
                'msg'    => 'Appointment reschedule successfully.'

            ) ) );

        } else {

            wp_die( json_encode( array(
                'status' => 'failed',
                'msg'    => 'Something went wrong !'

            ) ) );
        }
    }

    /*
     * AJAX  call to add appointments from admin section
     */
    public function gh_add_appointment()
    {
        if ( ! current_user_can( 'add_appointment' ) ){
            $response = array( 'msg' => 'Your user role does not have the required permissions to add appointment.' );
            wp_die( json_encode($response) );
        }

        // ADD APPOINTMENTS using AJAX.

        $start              = $_POST['start_time'] ;
        $end                = $_POST['end_time']  ;
        $contact_id         = intval( $_POST['id'] );
        $note               = sanitize_text_field( $_POST['note'] );
        $appointment_name   =  sanitize_text_field( $_POST [ 'appointment_name'] );
        $calendar_id        =  sanitize_text_field( $_POST [ 'calendar_id'] );

        // perform insert operation
        $appointment_id  = $this->db->add( array (
            'contact_id'    => $contact_id,
            'calendar_id'   => $calendar_id,
            'name'          => $appointment_name,
            'status'        => 'pending',
            'start_time'    => strtotime($start),
            'end_time'      => strtotime($end)
            ));

        // Insert meta
        if ( $appointment_id === false ){

            $response = array( 'msg' => 'Something went wrong. Appointment not created !' );
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
                    'start'      => $start,
                    'end'        => $end,
                    'constraint' => 'businessHours',
                    'editable'   => true,
                    'allDay'     => false,
                    'url'        => admin_url( 'admin.php?page=gh_calendar&action=view_appointment&appointment=' . $appointment_id ),// link to view appointment page

                )
            );

        do_action('gh_calendar_add_appointment_admin',$appointment_id , 'create_admin' );
        wp_die( json_encode( $response ) );
    }

    /**
     * enqueue editor scripts for full calendar
     */
    public function scripts()
    {
        wp_enqueue_script( 'ajax-script',    WPGH_APPOINTMENT_ASSETS_FOLDER . '/js/appointments.js',    array('jquery'),     filemtime( WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/js/appointments.js' ) );
        wp_localize_script('ajax-script', 'ajax_object',array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'we_value' => 1234 ) );
        wp_enqueue_script( 'calender-moment',WPGH_APPOINTMENT_ASSETS_FOLDER . '/lib/fullcalendar/lib/moment.min.js', array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/lib/fullcalendar/lib/moment.min.js' ) );
        wp_enqueue_script( 'calender-main',  WPGH_APPOINTMENT_ASSETS_FOLDER . '/lib/fullcalendar/fullcalendar.js',   array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/lib/fullcalendar/fullcalendar.js') );
        wp_enqueue_style ( 'calender-css',   WPGH_APPOINTMENT_ASSETS_FOLDER . '/lib/fullcalendar/fullcalendar.css',  array(), filemtime(WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/lib/fullcalendar/fullcalendar.css') );

    }

    /*
     *  Add sub menu in Calendar in Groundhogg Plugin.
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
                ?>
                <a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=add' ); ?>"><?php _e( 'Add New' ); ?></a>
                <?php
                break;
            case 'view_appointment':
                _e( 'Appointment' , 'groundhogg' );
                break;
            case 'add_appointment':
                _e( 'Add Appointment' , 'groundhogg' );
                break;
            case 'view':
                _e( 'Calendar' , 'groundhogg' );
                ?> <a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar='.$_GET['calendar'] ); ?>"><?php _e( 'Add New Appointment' ); ?></a><?php
                 break;
            default:
                _e( 'Calendars', 'groundhogg' );
                ?>
                <a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=add' ); ?>"><?php _e( 'Add New' ); ?></a>
                <?php
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

            case 'delete':

                if ( ! current_user_can( 'delete_calendar' ) ){
                    wp_die( WPGH()->roles->error( 'delete_calendar' ) );
                }
                $this->delete_calendar();
                break;


            case 'view':

                if ( ! current_user_can( 'view_appointment' ) ){
                    wp_die( WPGH()->roles->error( 'view_appointment' ) );
                }

                if( isset( $_GET['calendar_id'] ) )
                {
                    $this->view_appointments();
                }
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

                    $this->notices->add( 'success', __( 'Appointment updated successfully !', 'groundhogg' ), 'success' );
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
                    //get appointment
                    $appointment = $this->db->get($appointment_id);
                    if($appointment == null) {
                        $this->notices->add( 'NO_APPOINTMENT', __( "Appointment not found!", 'groundhogg' ), 'error' );
                        return;
                    }

                    do_action('gh_calendar_appointment_deleted', $appointment_id , 'deleted' );

                    $status = $this->db->delete($appointment_id);
                    if ( ! $status ){
                        wp_die( 'Something went wrong' );
                    }
                    $this->notices->add( 'success', __( 'Appointment deleted successfully !', 'groundhogg' ), 'success' );
                    wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar=' . $appointment->calendar_id ) );
                    die();
                }
                break;
            case 'cancel':


                if ( ! current_user_can( 'edit_appointment' ) ){
                    wp_die( WPGH()->roles->error( 'edit_appointment' ) );
                }

                if ( isset( $_GET[ 'appointment' ] ) ) {

                    $appointment_id = intval( $_GET[ 'appointment' ] );
                    //get appointment
                    $appointment = $this->db->get($appointment_id);
                    if($appointment == null) {
                        $this->notices->add( 'NO_APPOINTMENT', __( "Appointment not found!", 'groundhogg' ), 'error' );
                        return;
                    }
                    //update status

                    $status = $this->db->update($appointment_id,array('status'=>'cancelled'));
                    if ( ! $status ){
                        wp_die( 'Something went wrong' );
                    }

                    do_action('gh_calendar_appointment_cancelled',$appointment_id , 'cancelled' );
                    $this->notices->add( 'success', __( 'Appointment updated successfully !', 'groundhogg' ), 'success' );
                    wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=add_appointment&calendar=' . $appointment->calendar_id ) );
                    die();
                }

                break;
        }

        set_transient( 'gh_last_action', $this->get_action(), 30 );

        if ( $this->get_action() === 'edit' || $this->get_action() === 'add' ){
            return true;
        }

        if ( $this->get_calendars() ){
            $base_url = add_query_arg( 'ids', urlencode( implode( ',', $this->get_calendars() ) ), $base_url );
        }

        wp_redirect( $base_url );
        die();
    }

    /*
     *  Handles Delete calendar request form list of calendar table
     */

    private function delete_calendar()
    {
        //todo delete the calendar


        //get the ID of the calendar

        if ( isset( $_GET[ 'calendar' ] ) ){
            $calendar = intval( $_GET[ 'calendar' ] );
        }

        if ( ! isset( $calendar ) ){
            wp_die( __( "Please select a calendar to delete.", 'groundhogg' ) );
        }

        WPGH_APPOINTMENTS()->calendar->delete( $calendar );


    }

    /*
     *  Handles post request of updating calendar details..
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

//        if ( ! current_user_can( 'schedule_calendars' ) ){
//            wp_die( WPGH()->roles->error( 'schedule_calendars' ) );
//        }


        $calendar_id = intval($_POST[ 'calendar' ] );


        $args =  array(
            'user_id' =>  intval( $_POST['owner_id'] ),
            'name'    => sanitize_text_field( $_POST['name'] ),
        );

        if ( isset( $_POST['description'] ) ) {
            $args['description']  =  sanitize_text_field( $_POST['description'] );
        }

        //update operation

        $status  = WPGH_APPOINTMENTS()->calendar->update( $calendar_id,$args );
        //

        if ( ! $status ){
            wp_die( 'Something went wrong' );
        }

        //update meta

        // days
        if(isset( $_POST['checkbox'] ) ){
            WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id,'dow',$_POST['checkbox']) ;
        }
        // start time
        if(isset( $_POST['starttime'] ) ) {
            WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'start_time', sanitize_text_field($_POST['starttime']));
        }
        //end time
        if(isset( $_POST['endtime'] ) ) {
            WPGH_APPOINTMENTS()->calendarmeta->update_meta($calendar_id, 'end_time', sanitize_text_field($_POST['endtime']));
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

//        if ( ! current_user_can( 'schedule_calendars' ) ){
//            wp_die( WPGH()->roles->error( 'schedule_calendars' ) );
//        }

        $args =  array(
          'user_id' =>  intval( $_POST['owner_id'] ),
          'name'    => sanitize_text_field( $_POST['name'] ),
        );

        if ( isset( $_POST['description'] ) ) {
            $args['description']  =  sanitize_text_field( $_POST['description'] );
        }


        // ADD OPERATION
        $calendar_id = WPGH_APPOINTMENTS()->calendar->add( $args ) ;
        //META OPERATION

        if ( ! $calendar_id ){
            wp_die( 'Something went wrong' );
        }

        // Enter metadata of calendar

        // days
        if(isset( $_POST['checkbox'] ) ){
            WPGH_APPOINTMENTS()->calendarmeta->add_meta($calendar_id,'dow',$_POST['checkbox']) ;
        }
        // start time
        if(isset( $_POST['starttime'] ) ) {
            WPGH_APPOINTMENTS()->calendarmeta->add_meta($calendar_id, 'start_time', sanitize_text_field($_POST['starttime']));
        }
        //end time
        if(isset( $_POST['endtime'] ) ) {
            WPGH_APPOINTMENTS()->calendarmeta->add_meta($calendar_id, 'end_time', sanitize_text_field($_POST['endtime']));
        }

        $this->notices->add( 'success', __( 'New calendar added!', 'groundhogg' ), 'success' ); // not working
        wp_redirect( admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar_id ) );
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
     * Display the reporting page
     */
    public function view_appointment()
    {
        if ( ! current_user_can( 'view_appointment' ) ){
            wp_die( WPGH()->roles->error( 'view_appointment' ) );
        }

        include dirname( __FILE__ ) . '/view-appointmnent.php';
    }
    public function add_appointment()
    {
        include dirname( __FILE__ ) . '/add-appointment.php';
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
}