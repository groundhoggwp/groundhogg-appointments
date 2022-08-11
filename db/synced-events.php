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
 * @since       File available since Release 2.0
 *
 * @subpackage  includes/DB
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @package     Includes
 */
class Synced_Events extends DB {

	public function get_db_suffix() {
		return 'gh_synced_events';
	}

	public function get_primary_key() {
		return 'cache_key';
	}

	public function get_db_version() {
		return '2.0';
	}

	public function get_object_type() {
		return 'synced_event_query';
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
			'cache_key' => '%s',
			'results'   => '%s',
			'expires'   => '%d',
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
			'cache_key' => '',
			'results'   => '',
			'expires'   => time() + ( 5 * MINUTE_IN_SECONDS ),
		);
	}

	public function force_drop() {

		delete_option( $this->table_name . '_db_version' );

		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS " . $this->table_name );
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
        cache_key varchar({$this->get_max_index_length()}) NOT NULL,
        results longtext NOT NULL,
        expires bigint(20) unsigned NOT NULL,                
        PRIMARY KEY (cache_key)
		) {$this->get_charset_collate()};";
		dbDelta( $sql );
		update_option( $this->table_name . '_db_version', $this->version );
	}

}
