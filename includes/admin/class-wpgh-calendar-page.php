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


    public function __construct()
    {

        add_action( 'admin_menu', array( $this, 'register' ) );

        if ( isset( $_GET['page'] ) && $_GET[ 'page' ] === 'gh_calendar' ){
            add_action( 'init' , array( $this, 'process_action' )  );
            add_action( 'admin_enqueue_scripts' , array( $this, 'scripts' )  );
            $this->notices = WPGH()->notices;
        }
    }

    /**
     * enqueue editor scripts
     */
    public function scripts()
    {
        // load calendar files
        wp_enqueue_style( 'calender-moment', plugins_url( '../../assets/lib/fullcalendar/fullcalendar.css', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '../../assets/lib/fullcalendar/fullcalendar.css' ) );
        wp_enqueue_script( 'calender-moment', plugins_url( '../../assets/lib/fullcalendar/lib/moment.min.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '../../assets/lib/fullcalendar/lib/moment.min.js' ) );
        wp_enqueue_script( 'calender-main', plugins_url( '../../assets/lib/fullcalendar/fullcalendar.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '../../assets/lib/fullcalendar/fullcalendar.js' ) );
        wp_enqueue_script( 'calender-ui', plugins_url( '../../assets/lib/fullcalendar/lib/jquery-ui.min.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '../../assets/lib/fullcalendar/lib/jquery-ui.min.js' ) );

    }

    public function register()
    {
        $page = add_submenu_page(
            'groundhogg',
            'Calendars',
            'Calendars',
            'view_broadcasts',
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
                break;
            default:
                _e( 'Calendars', 'groundhogg' );
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

//                if ( ! current_user_can( 'schedule_calendars' ) ){
//                    wp_die( WPGH()->roles->error( 'schedule_calendars' ) );
//                }
                if ( isset( $_POST[ 'add' ] ) ) {
                    $this->add_calendar();
                }

                break;
            case 'edit':

//                if ( ! current_user_can( 'schedule_calendars' ) ){
//                    wp_die( WPGH()->roles->error( 'schedule_calendars' ) );
//                }
                if ( isset( $_POST[ 'update' ] ) ) {
                    $this->update_calendar();
                }

                break;

            case 'delete':

//                if ( ! current_user_can( 'schedule_calendars' ) ){
//                    wp_die( WPGH()->roles->error( 'schedule_calendars' ) );
//                }
                $this->delete_calendar();
                break;
            case 'view':

                if( isset( $_GET['calendar_id'] ) )
                {
                    $this->view_appointments();
                }
                break;
            case 'manage':

                echo  'hello';

//                if ( ! current_user_can( 'cancel_calendars' ) ){
//                    wp_die( WPGH()->roles->error( 'cancel_calendars' ) );
//                }
//
//                foreach ( $this->get_calendars() as $id ){
//                    $calendar = new WPGH_Broadcast( $id );
//                    $calendar->cancel();
//                }
//
//                $this->notices->add( 'cancelled', __( count( $this->get_calendars() ) . ' Broadcast(s) Cancelled.', 'groundhogg' ) );

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
     * Schedule a new calendar
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

    public  function view_appointments()
    {
        if ( isset($_GET['calendar_id'] ) ) {
            include dirname(__FILE__) . '/view-appointments.php';
        } else {
            $this->table();
        }
    }

    /**
     * Display the scheduling page
     */
    public function add()
    {
//        if ( ! current_user_can( 'schedule_calendars' ) ){
//            wp_die( WPGH()->roles->error( 'schedule_calendars' ) );
//        }

        include dirname(__FILE__) . '/add-calendar.php';
    }

    /**
     * Display the reporting page
     */
    public function edit()
    {


//        if ( ! current_user_can( 'view_calendars' ) ){
//            wp_die( WPGH()->roles->error( 'view_calendars' ) );
//        }

        include dirname( __FILE__ ) . '/edit-calendar.php';
    }

    /**
     * Display the screen content
     */
    public function page()
    {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php $this->get_title(); ?></h1><a class="page-title-action aria-button-if-js" href="<?php echo admin_url( 'admin.php?page=gh_calendar&action=add' ); ?>"><?php _e( 'Add New' ); ?></a>
            <?php $this->notices->notices(); ?>
            <hr class="wp-header-end">
            <?php switch ( $this->get_action() ){
                case 'add':
                    $this->add();
                    break;
                case 'edit':
                    $this->edit();
                    break;
                case 'view':
                    //calendar page with appointments in it.
                    $this->view_appointments();
                    break;
                default:
                    $this->table();
            } ?>
        </div>
        <?php
    }
}