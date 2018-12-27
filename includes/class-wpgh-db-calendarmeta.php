<?php
/**
 * CalendarMeta Table.
 *
 * Store meta information about calendar.
 *
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WPGH_DB_Calendar_Meta Class
 *
 * @since 2.1
 */
class WPGH_DB_Calendar_Meta extends WPGH_DB  {

    /**
     * The name of the cache group.
     *
     * @access public
     * @since  2.8
     * @var string
     */
    public $cache_group = 'calendar_meta';

    /**
     * Get things started
     *
     * @access  public
     * @since   2.1
     */
    public function __construct() {

        global $wpdb;

        $this->table_name  = $wpdb->prefix . 'gh_calendarmeta';

        $this->primary_key = 'meta_id';
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
            'meta_id'       => '%d',
            'calendar_id'   => '%s',
            'meta_key'      => '%s',
            'meta_value'    => '%s',

        );
    }


    public function register_table() {
        global $wpdb;
        $wpdb->calendarmeta = $this->table_name;
    }


    /**
     * Retrieve email meta field for a email.
     *
     * For internal use only. Use EDD_Contact->get_meta() for public usage.
     *
     * @param   int    $email_id   Contact ID.
     * @param   string $meta_key      The meta key to retrieve.
     * @param   bool   $single        Whether to return a single value.
     * @return  mixed                 Will be an array if $single is false. Will be value of meta data field if $single is true.
     *
     * @access  private
     * @since   2.6
     */
    public function get_meta( $calendar_id = 0, $meta_key = '', $single = false ) {
        $calendar_id = $this->sanitize_calendar_id( $calendar_id );
        if ( false === $calendar_id ) {
            return false;
        }

        return get_metadata( 'calendar', $calendar_id, $meta_key, $single );
    }

    public function add_meta( $calendar_id = 0, $meta_key = '', $meta_value, $unique = false ) {
        $calendar_id = $this->sanitize_calendar_id( $calendar_id );
        if ( false === $calendar_id ) {
            return false;
        }
        return add_metadata( 'calendar', $calendar_id, $meta_key, $meta_value, $unique );
    }

    public function update_meta( $calendar_id = 0, $meta_key = '', $meta_value, $prev_value = '' ) {
        $calendar_id = $this->sanitize_calendar_id( $calendar_id );
        if ( false === $calendar_id ) {
            return false;
        }

        return update_metadata( 'calendar', $calendar_id, $meta_key, $meta_value, $prev_value );
    }

    public function delete_meta( $calendar_id = 0, $meta_key = '', $meta_value = '' ) {
        return delete_metadata( 'calendar', $calendar_id, $meta_key, $meta_value );
    }


    private function sanitize_calendar_id( $calendar_id ) {
        if ( ! is_numeric( $calendar_id ) ) {
            return false;
        }

        $calendar_id = (int) $calendar_id;

        // We were given a non positive number
        if ( absint( $calendar_id ) !== $calendar_id ) {
            return false;
        }

        if ( empty( $calendar_id ) ) {
            return false;
        }

        return absint( $calendar_id );

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
        meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        calendar_id bigint(20) unsigned NOT NULL,
        meta_key varchar(255) DEFAULT NULL,
        meta_value longtext,
        PRIMARY KEY (meta_id),
        KEY calendar_id (calendar_id),
        KEY meta_key (meta_key)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;";

        dbDelta( $sql );
        update_option( $this->table_name . '_db_version', $this->version );
    }

}