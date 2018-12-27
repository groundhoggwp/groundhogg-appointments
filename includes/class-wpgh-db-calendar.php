<?php
/**
 * calendar DB
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

class WPGH_DB_Calendar extends WPGH_DB
{
    /**
     * The name of the cache group.
     *
     * @access public
     * @since  2.8
     * @var string
     */
    public $cache_group = 'calendar';

    /**
     * Get things started
     *
     * @access  public
     * @since   2.1
     */
    public function __construct() {

        global $wpdb;
        $this->table_name  = $wpdb->prefix . 'gh_calendar';
        $this->primary_key = 'ID';
        $this->version     = '1.0';
    }

    /**
     * Get columns and formats
     *
     * @access  public
     * @since   2.1
     */
    public function get_columns() {
        return array(
            'ID'             => '%d',
            'user_id'        => '%d',
            'name'           => '%s',
            'description'    => '%s',
        );
    }

    /**
     * Get default column values
     *
     * @access  public
     * @since   2.1
     */
    public function get_column_defaults() {
        return array(
            'ID'             => 0,
            'user_id'        => 0,
            'name'           => '',
            'description'    => '',
        );
    }


    /**
     * Add a calendar
     *
     * @access  public
     * @since   2.1
     */
    public function add( $data = array() ) {

        $args = wp_parse_args(
            $data,
            $this->get_column_defaults()
        );

        if( empty( $args['name'] ) ) {
            return false;
        }

        $args[ 'slug' ] = sanitize_title( $args[ 'name' ] );

        if ( $this->exists( $args[ 'slug' ], 'slug' ) ){
            return $this->get_calendar_by( 'slug', $args[ 'slug' ] );
        }
        return $this->insert( $args, 'calendar' );
    }

    /**
     * Insert a new calendar
     *
     * @access  public
     * @since   2.1
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
     * @since   2.1
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
     * Delete a calendar
     *
     * @access  public
     * @since   2.3.1
     */
    public function delete( $id = false ) {

        if ( empty( $id ) ) {
            return false;
        }

        $calendar = $this->get_calendar_by( 'ID', $id );

        if ( $calendar->ID > 0 ) {

            global $wpdb;

            /* delete the actual calendar */
            $result = $wpdb->delete( $this->table_name, array( 'ID' =>$calendar->ID ), array( '%d' ) );

            if ( $result ) {
                $this->set_last_changed();
                do_action( 'wpgh_delete_calendar', $calendar->ID );
            }
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Checks if a calendar exists
     *
     * @access  public
     * @since   2.1
     */
    public function exists( $value = 0, $field = 'ID' ) {

        $columns = $this->get_columns();
        if ( ! array_key_exists( $field, $columns ) ) {
            return false;
        }
        $calendar = $this->get_calendar_by( $field, $value );
        return ! empty( $calendar ) ;
    }

    /**
     * Retrieves the calendar by the ID.
     *
     * @param $id
     *
     * @return mixed
     */
    public function get_calendar( $id )
    {
        return $this->get_calendar_by( 'ID', $id );
    }

    /**
     * Retrieves a single calendar from the database
     *
     * @access public
     * @since  2.3
     * @param  string $field id or email
     * @param  mixed  $value  The Customer ID or email to search
     * @return mixed          Upon success, an object of the calendar. Upon failure, NULL
     */
    public function get_calendar_by( $field = 'ID', $value = 0 ) {
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

        } else if ( 'slug' == $field )
        {
            if ( ! is_string( $value ) ) {
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
     * @since   2.1
     */
    public function get_calendars() {

        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM $this->table_name ORDER BY $this->primary_key DESC" );
        return $results;
    }

    /**
     * Count the total number of calendar in the database
     *
     * @access  public
     * @since   2.1
     */
    public function count( $args = array() ) {
        return count( $this->get_calendars() );
    }

    /**
     * Sets the last_changed cache key for calendars.
     *
     * @access public
     * @since  2.8
     */
    public function set_last_changed() {
        wp_cache_set( 'last_changed', microtime(), $this->cache_group );
    }

    /**
     * Retrieves the value of the last_changed cache key for calendars.
     *
     * @access public
     * @since  2.8
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

    /**
     * Create the table
     *
     * @access  public
     * @since   2.1
     */
    public function create_table() {

        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql = "CREATE TABLE " . $this->table_name . " (
        ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,        
        name mediumtext NOT NULL,
        description text NOT NULL,        
        PRIMARY KEY (ID)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

        dbDelta( $sql );

        update_option( $this->table_name . '_db_version', $this->version );
    }

}