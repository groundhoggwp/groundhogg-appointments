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
		return 'event_id';
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
	}


	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   2.0
	 */
	public function get_columns() {
		return array(
			'event_id'           => '%s',
			'summary'            => '%s',
			'local_gcalendar_id' => '%d',
			'google_calendar_id' => '%s',
			'status'             => '%s',
			'start_time'         => '%d',
			'end_time'           => '%d',
			'start_time_pretty'  => '%s',
			'end_time_pretty'    => '%s',
			'last_synced'        => '%d',
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
			'event_id'           => '',
			'summary'            => '',
			'local_gcalendar_id' => 0,
			'google_calendar_id' => '',
			'status'             => '',
			'start_time'         => 0,
			'end_time'           => 0,
			'start_time_pretty'  => '',
			'end_time_pretty'    => '',
			'last_synced'        => time(),
		);
	}

	/**
	 * @param $start
	 * @param $end
	 * @param $local_gcalendar_id
	 *
	 * @return bool
	 */
	public function time_available( $start, $end, $local_gcalendar_id ) {
		global $wpdb;

		// 1. Start time of an existing event is within the range of the slot
		// 2. End time of an existing event is within the range of the slot
		// 3. An existing event is within the bounds of the slot
		// 4. Slot is within the bounds of an existing event

		$results = $wpdb->get_results( sprintf( '
	SELECT * FROM %4$s
		WHERE ( 
		    ( start_time > %1$d AND start_time < %2$d )
			OR ( end_time >  %1$d AND end_time < %2$d )
		    OR ( start_time <= %1$d AND end_time >= %2$d ) 
		    OR ( start_time >= %1$d AND end_time <= %2$d ) 
		) 
		AND local_gcalendar_id = %3$d AND status = "%5$s"',
			$start, $end, $local_gcalendar_id, $this->table_name, 'confirmed' ) );

		return empty( $results );
	}

	/**
	 * Delete older events
	 */
	public function delete_old_events() {

		global $wpdb;

		// Delete events which haven't been synced in at least an hour (assume they were deleted in Google)
		// OR events older than the current time
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE `end_time` < %s OR `last_synced` < %s", time(), time() - HOUR_IN_SECONDS ) );

		// Cache compat
		$this->cache_set_last_changed();

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
        event_id varchar({$this->get_max_index_length()}) NOT NULL,
        summary mediumtext NOT NULL,
        google_calendar_id varchar({$this->get_max_index_length()}) NOT NULL,
        local_gcalendar_id bigint(20) unsigned NOT NULL,
        status varchar(20) NOT NULL,
        start_time bigint(20) unsigned NOT NULL,
        end_time bigint(20) unsigned NOT NULL,
        start_time_pretty datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        end_time_pretty datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,                
        last_synced bigint(20) unsigned NOT NULL,                
        PRIMARY KEY (event_id),
        KEY google_calendar_id (google_calendar_id),
        KEY start_time (start_time),
        KEY end_time (end_time)
		) {$this->get_charset_collate()};";
		dbDelta( $sql );
		update_option( $this->table_name . '_db_version', $this->version );
	}

}