<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class WPGH_Appointment_Benchmark extends WPGH_Funnel_Step
{
    /**
     * get things started.
     *
     * WPGH_Appointment_Benchmark constructor
     */
    public function __construct()
    {

        $this->type     = 'gh_appointments';
        $this->group    = 'benchmark';
        $this->name     = __( 'Groundhogg Appointment' );
        $this->icon     = 'form-filled.png' ;
        parent::__construct();

        // ation hooks
        add_action('gh_calendar_add_appointment_client'   , array($this,'complete') , 10 , 2);
        add_action('gh_calendar_update_appointment_admin' , array($this,'complete') , 10 , 2 );
        add_action('gh_calendar_add_appointment_admin'    , array($this,'complete') , 10 , 2 );
        add_action('gh_calendar_appointment_approved'     , array($this,'complete') , 10 , 2 );
        add_action('gh_calendar_appointment_cancelled'    , array($this,'complete') , 10 , 2 );
        add_action('gh_calendar_appointment_deleted'      , array($this,'complete') , 10 , 2 );

    }

    /**
     * Output the settings for the step, dropdown of all available calendars forms...
     *
     * @param $step WPGH_Step
     */
    public function settings( $step )
    {
        $action     = $step->get_meta( 'action' );
        $calendar   = $step->get_meta( 'calendar' );
        $calendar_args = array(
            'id'        => $step->prefix( 'calendar' ),
            'name'      => $step->prefix( 'calendar' ),
            'selected'  => array($calendar)
        );
        ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><?php echo esc_html__( 'Run when appointment book in this calendar:', 'groundhogg' ); ?></th>
                        <td><?php echo $this->dropdown_calendar( $calendar_args ); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Run when Appointment is:' ); ?></th>
                        <td>
                            <?php
                                $options = array(
                                    'create_client'      => __( 'Created by client' ),
                                    'create_admin'       => __( 'Created by Admin' ),
                                    'reschedule_admin'   => __( 'Reschedule by admin' ),
                                    'approved'           => __( 'Appointment Approved' ),
                                    'deleted'            => __( 'Appointment Deleted' ),
                                    'cancelled'          => __( 'Appointment Cancelled' ),
                                );
                                $args = array(
                                    'id'        => $step->prefix( 'action' ),
                                    'name'      => $step->prefix( 'action' ),
                                    'options'   => $options,
                                    'selected'  => $action
                                );
                                echo WPGH()->html->dropdown( $args );
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php
    }

    /**
     * Bind list of calendar form database for select
     *
     * @param $args
     * @return string
     */
    public function dropdown_calendar( $args )
    {
        $a = wp_parse_args( $args, array(
            'name'              => 'calendar',
            'id'                => 'calendar',
            'selected'          => 0,
            'class'             => 'gh_calendar-picker gh-select2',
            'multiple'          => false,
            'placeholder'       => __( 'Please Select a calendar', 'groundhogg' ),
            'tags'              => false,
        ) );
        $calendars = WPGH_APPOINTMENTS()->calendar->get_calendars();
        foreach ($calendars as $calendar ){
            $a[ 'data' ][ $calendar->ID ] = $calendar->name;
        }
        return WPGH()->html->select2( $a );
    }

    /**
     * save step selection inside database
     *
     * @param WPGH_Step $step
     */
    public function save( $step )
    {
        if ( isset( $_POST[ $step->prefix( 'action' ) ] ) ){
            $step->update_meta( 'action', sanitize_key( $_POST[ $step->prefix( 'action' ) ] ) );
        }
        if ( isset( $_POST[ $step->prefix( 'calendar' ) ] ) ){
            $step->update_meta( 'calendar', sanitize_key( $_POST[ $step->prefix( 'calendar' ) ] ) );
        }
    }

    /**
     * Return true always
     *
     * @param WPGH_Contact $contact
     * @param WPGH_Event $event
     * @return bool
     */
    public function run( $contact, $event )
    {
        # silence is golden
        return true;
    }

    /**
     * Complete benchmark action.
     *
     * @param $id
     * @param $action
     */
    function complete( $id , $action )
    {
        $appointment = WPGH_APPOINTMENTS()->appointments->get_appointment($id);
        $contact = wpgh_get_contact( $appointment->contact_id );
        if ( ! $contact ){
            return;
        }
        $action = sanitize_key( strtolower ( $action ) );
        $steps = WPGH()->steps->get_steps( array( 'step_type' => $this->type, 'step_group' => $this->group ) );
        if ( empty( $steps ) ) {
            return;
        }
        foreach ( $steps as $step ){
            $step = new WPGH_Step( $step->ID );
            if ( $step->can_complete( $contact ) && $step->get_meta( 'action' ) === $action && intval( $step->get_meta( 'calendar' ) ) === intval( $appointment->calendar_id )   ){
                $step->enqueue( $contact );
            }
        }
    }

}