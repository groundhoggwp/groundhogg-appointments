<?php

namespace GroundhoggBookingCalendar\DB;

use Groundhogg\DB\DB;
use GroundhoggBookingCalendar\Plugin;
use function GroundhoggBookingCalendar\generate_uuid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Appointments DB
 *
 * Store appointment info
 *
 * @package     Includes
 * @subpackage  includes/DB
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @since       File available since Release 2.0
 *
 */
class Appointments extends DB {
	public function get_db_suffix() {
		return 'gh_appointments';
	}

	public function get_primary_key() {
		return 'ID';
	}

	public function get_db_version() {
		return '2.0';
	}

	public function get_object_type() {
		return 'appointment';
	}

	/**
	 * Clean up DB events when this happens.
	 */
	protected function add_additional_actions() {
		add_action( 'groundhogg/db/post_delete/contact', [ $this, 'contact_deleted' ] );
		add_action( 'groundhogg/db/post_delete/calendar', [ $this, 'calendar_deleted' ] );
	}

	public function get_date_key() {
		return 'start_time';
	}

	/**
	 * @param $start
	 * @param $end
	 * @param $local_gcalendar_id
	 *
	 * @return bool
	 */
	public function time_available( $start, $end, $calendar_id ) {
		global $wpdb;

		// 1. Start time of an existing event is within the range of the slot
		// 2. End time of an existing event is within the range of the slot
		// 3. An existing event is within the bounds of the slot
		// 4. Slot is within the bounds of an existing event

		$results = $wpdb->get_results( sprintf( '
	SELECT * FROM %4$s
		WHERE ( 
		    ( start_time > %1$d AND start_time < %2$d )
			OR ( end_time > %1$d AND end_time < %2$d )
		    OR ( start_time <= %1$d AND end_time >= %2$d ) 
		    OR ( start_time >= %1$d AND end_time <= %2$d ) 
		) 
		AND calendar_id = %3$d AND status = "%5$s"',
			$start, $end, $calendar_id, $this->table_name, 'scheduled' ) );

		return empty( $results );
	}

	public function query( $data = [], $ORDER_BY = '', $from_cache = true ) {

		// Reps can't see others appointments
		if ( current_user_can( 'view_appointments' ) && ! current_user_can( 'view_others_appointments' ) ){
			$data['owner_id'] = get_current_user_id();
		}

		return parent::query( $data, $ORDER_BY, $from_cache );
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
			'contact_id'  => '%d',
			'uuid'        => '%s',
			'calendar_id' => '%d',
			'status'      => '%s',
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
			'contact_id'  => 0,
			'uuid'        => wp_generate_uuid4(),
			'calendar_id' => 0,
			'status'      => 'scheduled',
			'start_time'  => 0,
			'end_time'    => 0,
		);
	}

	/**
	 * Delete appointments associated with the calendars...
	 *
	 * @param $id int Calendar ID
	 *
	 * @return void
	 */
	public function calendar_deleted( $id ) {
		if ( is_numeric( $id ) ) {
			$appointments = $this->query( [ 'calendar_id' => $id ] );
			foreach ( $appointments as $appointment ) {
				$this->delete( absint( $appointment->ID ) );
			}
		}
	}

	/**
	 * Delete appointments associated with the calendars...
	 *
	 * @param $id int Calendar ID
	 *
	 * @return void
	 */
	public function contact_deleted( $id ) {
		if ( is_numeric( $id ) ) {
			$appointments = $this->query( [ 'contact_id' => $id ] );
			foreach ( $appointments as $appointment ) {
				$this->delete( absint( $appointment->ID ) );
			}
		}
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
        uuid varchar({$this->get_max_index_length()}) NOT NULL,
        contact_id bigint(20) unsigned NOT NULL,
        calendar_id bigint(20) unsigned NOT NULL,
        status VARCHAR(20) NOT NULL,
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
