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
class Google_Calendars extends DB {

	public function get_db_suffix() {
		return 'gh_google_calendars';
	}

	public function get_primary_key() {
		return 'ID';
	}

	public function get_db_version() {
		return '2.0';
	}

	public function get_object_type() {
		return 'google_calendar';
	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   2.0
	 */
	public function get_columns() {
		return array(
			'ID'                 => '%d',
			'name'               => '%s',
			'google_calendar_id' => '%s',
			'google_account_id'  => '%d',
			'connection_id'      => '%d',
			'sync_status'        => '%s',
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
			'ID'                 => 0,
			'name'               => '',
			'google_calendar_id' => '',
			'google_account_id'  => 0,
			'connection_id'      => 0,
			'last_synced'        => 0,
			'sync_status'        => 'off',
		);
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
        name mediumtext NOT NULL,
        google_calendar_id varchar({$this->get_max_index_length()}) NOT NULL,
        google_account_id bigint(20) unsigned NOT NULL,
        connection_id bigint(20) unsigned NOT NULL,
        sync_status varchar(20) NOT NULL,
        time_zone varchar({$this->get_max_index_length()}) NOT NULL,
        last_synced bigint(20),              
        PRIMARY KEY (ID),
        KEY google_calendar_id (google_calendar_id),
        KEY google_account_id (google_calendar_id)
		) {$this->get_charset_collate()};";
		dbDelta( $sql );
		update_option( $this->table_name . '_db_version', $this->version );
	}

}