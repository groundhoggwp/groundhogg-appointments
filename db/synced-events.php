<?php

namespace GroundhoggBookingCalendar\DB;

use Groundhogg\DB\DB;
use GroundhoggBookingCalendar\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Synced events
 *
 * rather than store events from google calendar and other synced calendars in the main appointments table, add them to this other table
 * which can be easily truncated and manipulated
 *
 * @package     Includes
 * @subpackage  includes/DB
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @since       File available since Release 2.0
 *
 */
class Synced_Events extends DB {

	public function get_db_suffix() {
		return 'gh_synced_events';
	}

	public function get_primary_key() {
		return 'ID';
	}

	public function get_db_version() {
		return '2.0';
	}

	public function get_object_type() {
		return 'synced_event';
	}

	/**
	 * Clean up DB events when this happens.
	 */
	protected function add_additional_actions() {
		add_action( 'groundhogg/db/post_delete/calendar', [ $this, 'calendar_deleted' ] );
	}


	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   2.0
	 */
	public function get_columns() {
		return array(
			'ID'          => '%d',
			'event_id'    => '%s',
			'calendar_id' => '%d',
			'start_time'  => '%d',
			'end_time'    => '%d',
		);
	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   2.0
	 */
	public function get_column_defaults() {
		return array(
			'ID'          => 0,
			'event_id'    => '',
			'calendar_id' => 0,
			'start_time'  => 0,
			'end_time'    => 0,
		);
	}

	/**
	 * @param $a           int
	 * @param $b           int
	 * @param $calendar_id int
	 *
	 * @return array|null|object
	 */
	public function appointments_exist_in_range( $a, $b, $calendar_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE ( (start_time BETWEEN %d AND %d) OR (end_time BETWEEN %d AND %d) ) AND calendar_id = %d",
			$a, $b, $a, $b, absint( $calendar_id ) ) );

		return $results;
	}

	/**
	 * @param $a           int
	 * @param $b           int
	 * @param $calendar_id int
	 *
	 * @return array|null|object
	 */
	public function appointments_exist_in_range_except_same_appointment( $a, $b, $calendar_id, $appointment_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->get_table_name()} WHERE ( (start_time BETWEEN %d AND %d) OR (end_time BETWEEN %d AND %d) ) AND calendar_id = %d AND ID != %d",
			$a, $b, $a, $b, absint( $calendar_id ), absint( $appointment_id ) ) );

		return $results;
	}

	/**
	 * Delete appointments associated with the calendars...
	 *
	 * @param $id int Calendar ID
	 *
	 * @return mixed
	 */
	public function calendar_deleted( $id ) {
		$appointments = $this->query( [ 'calendar_id' => $id ] );
		$result       = false;
		foreach ( $appointments as $appointment ) {
			$result = $this->delete( absint( $appointment->ID ) );
		}

		return $result;
	}


	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   2.0
	 */
	public function create_table() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$sql = "CREATE TABLE " . $this->table_name . " (
        ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id varchar({$this->get_max_index_length()}) NOT NULL,
        calendar_id bigint(20) unsigned NOT NULL,
        start_time bigint(20) unsigned NOT NULL,
        end_time bigint(20) unsigned NOT NULL,                
        PRIMARY KEY (ID),
        KEY start_time (start_time),
        KEY end_time (end_time)
		) {$this->get_charset_collate()};";
		dbDelta( $sql );
		update_option( $this->table_name . '_db_version', $this->version );
	}

}