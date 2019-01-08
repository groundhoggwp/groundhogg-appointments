<?php
/**
 * appointment  DB
 *
 * Store calendar
 *
 * @package     Includes
 * @subpackage  includes/DB
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @since       File available since Release 0.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPGH_DB_Appointments extends WPGH_DB
{
    /**
     * The name of the cache group.
     *
     * @access public
     * @since  1.0
     * @var string
     */
    public $cache_group = 'appointments';

    /**
     * Get things started
     *
     * @access  public
     * @since  1.0
     */
    public function __construct() {

        global $wpdb;
        $this->table_name  = $wpdb->prefix . 'gh_appointments';
        $this->primary_key = 'ID';
        $this->version     = '1.0';

        //todo delete appointments associated with a calendar
        add_action( 'wpgh_delete_calendar', array( $this, 'delete_appointments' ) );
    }

    /**
     * Get columns and formats
     *
     * @access  public
     * @since   1.0
     */
    public function get_columns() {
        return array(
            'ID'             => '%d',
            'contact_id'     => '%d',
            'calendar_id'    => '%d',
            'name'           => '%s',
            'status'         => '%s',
            'start_time'     => '%d',
            'end_time'       => '%d',
        );
    }

    /**
     * Get default column values
     *
     * @access  public
     * @since   1.0
     */
    public function get_column_defaults() {
        return array(
            'ID'             => 0,
            'contact_id'     => 0,
            'calendar_id'    => 0,
            'name'           => '',
            'status'         => 'pending',
            'start_time'     => 0,
            'end_time'       => 0,
        );
    }


    /**
     * Add a appointment
     *
     * @access  public
     * @since   1.0
     */
    public function add( $data = array() ) {

        $args = wp_parse_args(
            $data,
            $this->get_column_defaults()
        );

        if( empty( $args['contact_id'] ) ) {
            return false;
        }

        return $this->insert( $args, 'appointment' );
    }

    /**
     * Insert a new appointment
     *
     * @access  public
     * @since   1.0
     * @return  int
     */
    public function insert( $data, $type = '' ) {
        $result = parent::insert( $data, $type );
        if ( $result ) {
            $this->set_last_changed();
        }
        return $result;
    }

    /**
     * Update a calendar
     *
     * @access  public
     * @since   1.0
     * @return  bool
     */
    public function update( $row_id, $data = array(), $where = '' ) {
        $result = parent::update( $row_id, $data, $where );

        if ( $result ) {
            $this->set_last_changed();
        }

        return $result;
    }

    /**
     * Delete a appointment
     *
     * @access  public
     * @since   1.0
     */
    public function delete( $id = false ) {
        if ( empty( $id ) ) {
            return false;
        }
        $appointment = $this->get_appointment_by( 'ID', $id );

        if ( $appointment->ID > 0 ) {
            global $wpdb;
            /* delete the actual calendar */
            $result = $wpdb->delete( $this->table_name, array( 'ID' => $appointment->ID ), array( '%d' ) );

            if ( $result ) {
                $this->set_last_changed();
                do_action( 'wpgh_delete_appointment', $appointment->ID );
            }
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Delete appointments associated with the calendars...
     *
     * @param $id int Calendar ID
     * @return mixed
     */
    public function delete_appointments( $id )
    {
        global $wpdb;
        $IDS = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE calendar_id = %d", $id ) );
        $result = false;
        foreach ( $IDS as $id ){
           // $id = array_shift( $id );
            $result = $this->delete( $id->ID );
        }
        return $result;
    }


    /**
     * Checks if a calendar exists
     *
     * @access  public
     * @since   1.0
     */
    public function exists( $value = 0, $field = 'ID' ) {

        $columns = $this->get_columns();
        if ( ! array_key_exists( $field, $columns ) ) {
            return false;
        }
        $appointment = $this->get_appointment_by( $field, $value );
        return ! empty( $appointment ) ;
    }

    /**
     * Retrieves the appointment by the ID.
     *
     * @param $id
     *
     * @return mixed
     */
    public function get_appointment( $id )
    {
        return $this->get_appointment_by( 'ID', $id );
    }

    /**
     * Retrieves a single calendar from the database
     *
     * @access public
     * @since  1.0
     * @param  string $field id or email
     * @param  mixed  $value  The Customer ID or email to search
     * @return mixed          Upon success, an object of the calendar. Upon failure, NULL
     */
    public function get_appointment_by( $field = 'ID', $value = 0 ) {
        if ( empty( $field ) || empty( $value ) ) {
            return NULL;
        }
        if ( 'ID' == $field ) {
            // Make sure the value is numeric to avoid casting objects, for example,
            // to int 1.
            if ( ! is_numeric( $value ) ) {
                return false;
            }
            $value = intval( $value );
            if ( $value < 1 ) {
                return false;
            }
        }
        if ( ! $value ) {
            return false;
        }
        $results = $this->get_by( $field, $value );
        if ( empty( $results ) ) {
            return false;
        }
        return $results;
    }

    /**
     * Retrieve calendars from the database
     *
     * @access  public
     * @since   1.0
     */
    public function get_appointments() {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM $this->table_name ORDER BY $this->primary_key DESC" );
        return $results;
    }

    /**
     * Count the total number of calendar in the database
     *
     * @access  public
     * @since   1.0
     */
    public function count( ) {
        return count( $this->get_appointments() );
    }

    /**
     * Sets the last_changed cache key for calendars.
     *
     * @access public
     * @since  1.0
     */
    public function set_last_changed() {
        wp_cache_set( 'last_changed', microtime(), $this->cache_group );
    }

    /**
     * Retrieves the value of the last_changed cache key for calendars.
     *
     * @access public
     * @since  1.0
     */
    public function get_last_changed() {
        if ( function_exists( 'wp_cache_get_last_changed' ) ) {
            return wp_cache_get_last_changed( $this->cache_group );
        }
        $last_changed = wp_cache_get( 'last_changed', $this->cache_group );
        if ( ! $last_changed ) {
            $last_changed = microtime();
            wp_cache_set( 'last_changed', $last_changed, $this->cache_group );
        }
        return $last_changed;
    }

    public function get_appointments_by_args( $data = array(), $order = 'ID' ) {

        global  $wpdb;
        if ( ! is_array( $data ) )
            return false;
        $data = (array) $data;
        $extra = '';
        // Initialise column format array
        $column_formats = $this->get_columns();
        // Force fields to lower case
        $data = array_change_key_case( $data );
        // White list columns
        $data = array_intersect_key( $data, $column_formats );
        $where = $this->generate_where( $data );
        if ( empty( $where ) ){
            $where = "1=1";
        }
        $results = $wpdb->get_results( "SELECT * FROM $this->table_name WHERE $where $extra ORDER BY `$order` ASC" );
        return $results;
    }

    /**
     * Create the table
     *
     * @access  public
     * @since   1.0
     */
    public function create_table() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $sql = "CREATE TABLE " . $this->table_name . " (
        ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        contact_id bigint(20) unsigned NOT NULL,
        calendar_id bigint(20) unsigned NOT NULL,
        name mediumtext NOT NULL,
        status VARCHAR(20) NOT NULL,
        start_time bigint(20) unsigned NOT NULL,
        end_time bigint(20) unsigned NOT NULL,                
        PRIMARY KEY (ID)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";
        dbDelta( $sql );
        update_option( $this->table_name . '_db_version', $this->version );
    }

}