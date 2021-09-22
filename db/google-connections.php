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
class Google_Connections extends DB {

	public function get_db_suffix() {
		return 'gh_google_connections';
	}

	public function get_primary_key() {
		return 'ID';
	}

	public function get_db_version() {
		return '2.0';
	}

	public function get_object_type() {
		return 'google_connection';
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
			'ID'            => '%d',
			'account_id'    => '%d',
			'account_email' => '%s',
			'access_token'  => '%s',
			'refresh_token' => '%s',
			'created'       => '%d',
			'expires_in'    => '%d',
			'status'        => '%s',
			'added_by'      => '%d',
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
			'ID'            => 0,
			'account_id'    => 0,
			'account_email' => '',
			'access_token'  => '',
			'refresh_token' => '',
			'created'       => 0,
			'expires_in'    => 0,
			'status'        => 'active',
			'added_by'      => get_current_user_id(),
		);
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   2.0
	 */
	public function create_table() {

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$sql = "CREATE TABLE " . $this->table_name . " (
        ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        account_id bigint(20) unsigned NOT NULL,
        account_email varchar({$this->get_max_index_length()}) NOT NULL,
        access_token mediumtext NOT NULL,
        refresh_token mediumtext NOT NULL,
        expires_in bigint(20) unsigned NOT NULL,
        status varchar(20) NOT NULL,
        created bigint(20) unsigned NOT NULL,                
        added_by bigint(20) unsigned NOT NULL,                
        PRIMARY KEY (ID)
		) {$this->get_charset_collate()};";

		dbDelta( $sql );
		update_option( $this->table_name . '_db_version', $this->version );
	}

}