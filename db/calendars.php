<?php

namespace GroundhoggBookingCalendar\DB;

use Groundhogg\DB\DB;

class Calendars extends DB {
	public function get_db_suffix() {
		return 'gh_calendar';
	}

	public function get_primary_key() {
		return 'ID';

	}

	public function get_db_version() {
		return '2.0';
	}

	public function get_object_type() {
		return 'calendar';
	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function get_columns() {
		return array(
			'ID'          => '%d',
			'user_id'     => '%d',
			'name'        => '%s',
			'description' => '%s',
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
			'ID'          => 0,
			'user_id'     => 0,
			'name'        => '',
			'description' => '',
		);
	}

	/**
	 * Add a calendar
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function add( $data = array() ) {

		$args = wp_parse_args(
			$data,
			$this->get_column_defaults()
		);

		if ( empty( $args['name'] ) ) {
			return false;
		}

		$args['slug'] = sanitize_title( $args['name'] );

		if ( $this->exists( $args['slug'], 'slug' ) ) {
			return $this->get_by( 'slug', $args['slug'] );
		}

		return $this->insert( $args );
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
        user_id bigint(20) unsigned NOT NULL,        
        name mediumtext NOT NULL,
        description text NOT NULL,        
        PRIMARY KEY (ID)
		) {$this->get_charset_collate()};";
		dbDelta( $sql );
		update_option( $this->table_name . '_db_version', $this->version );
	}

}