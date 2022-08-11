<?php

namespace GroundhoggBookingCalendar\Classes;

use Groundhogg\Base_Object;
use Groundhogg\DB\DB;
use function Groundhogg\get_db;
use function Groundhogg\Ymd_His;

class Synced_Event_Query extends Base_Object {

	protected $query;
	protected $cache_key;

	public function __construct( $query_or_cache_key, $field = 'cache_key' ) {

		if ( is_array( $query_or_cache_key ) ) {

			$query = wp_parse_args( $query_or_cache_key, [
				'from'      => '',
				'to'        => '',
				'calendars' => []
			] );

			$this->query        = $query;
			$this->cache_key    = md5( serialize( $query ) );
			$query_or_cache_key = $this->cache_key;
			$field              = 'cache_key';
		}

		parent::__construct( $query_or_cache_key, $field );
	}

	public function get_id() {
		return $this->cache_key;
	}

	protected function post_setup() {
		$this->results = maybe_unserialize( $this->results );
	}

	protected function get_db() {
		return get_db( 'synced_events' );
	}

	/**
	 * If the cached results are expired
	 *
	 * @return bool
	 */
	public function is_expired() {
		return absint( $this->expires ) < time();
	}

	/**
	 * Get events from google
	 *
	 * @return array
	 */
	protected function get_events() {

		$gcal_query = [];

		if ( ! empty( $this->query['calendars'] ) ) {
			$gcal_query['ID'] = $this->query['calendars'];
		} else {

			// only use calendars which are actively used for availability
			$calendars_in_use = get_db( 'calendarmeta' )->query( [
				'meta_key' => 'google_calendar_list'
			] );

			$gcal_query['ID'] = array_unique( array_merge( ...array_map( 'maybe_unserialize', wp_list_pluck( $calendars_in_use, 'meta_value' ) ) ) );
		}

		$gcals = get_db( 'google_calendars' )->query( $gcal_query );

		$events = [];

		foreach ( $gcals as $gcal ) {
			$gcal = new Google_Calendar( $gcal );

			$events = array_merge( $events, $gcal->get_events( $this->query['from'], $this->query['to'] ) );

			if ( $gcal->has_errors() ) {
				foreach ( $gcal->get_errors() as $error ) {
					$this->add_error( $error );
				}
			}
		}

		return $events;
	}

	/**
	 * Maybe cache events query when performed
	 *
	 * @return array
	 */
	public function get_results() {

		if ( $this->exists() && ! $this->is_expired() ) {
			return $this->results;
		}

		$cache_key = $this->cache_key;

		if ( $this->is_expired() ) {
			$this->delete();
		}

		$results = $this->get_events();

		$this->create( [
			'cache_key' => $cache_key,
			'results'   => maybe_serialize( $results ),
			'expires'   => time() + ( 5 * MINUTE_IN_SECONDS )
		] );

		return $results;
	}

	/**
	 * Delete the object from the DB
	 *
	 * @return bool
	 */
	public function delete() {

		$id = $this->get_id();

		/**
		 * Fires before the object deleted...
		 *
		 * @param int         $object_id the ID of the object
		 * @param mixed[]     $data      just to make it compatible with the other crud actions
		 * @param Base_Object $object    the object class
		 */
		do_action( "groundhogg/{$this->get_object_type()}/pre_delete", $this->get_id(), $this->data, $this );

		if ( $this->get_db()->delete( [
			'cache_key' => $id
		] ) ) {
			unset( $this->data );
			unset( $this->ID );

			/**
			 * Fires after the object deleted...
			 *
			 * @param int $object_id the ID of the object
			 */
			do_action( "groundhogg/{$this->get_object_type()}/post_delete", $id );

			return true;
		}

		return false;
	}
}
