<?php

namespace GroundhoggBookingCalendar\Classes;

use Google\Service\Exception;
use Groundhogg\Base_Object;
use Groundhogg\DB\DB;
use function Groundhogg\get_db;
use function Groundhogg\get_time;
use function Groundhogg\Ymd_His;
use function GroundhoggBookingCalendar\get_max_booking_period;

class Synced_Event_Query extends Base_Object {

	protected $query;
	protected $cache_key;
	protected $connection;

	public function __construct( $query_or_cache_key, $field_or_connection = 'cache_key' ) {

		if ( is_array( $query_or_cache_key ) ) {

			$query = wp_parse_args( $query_or_cache_key, [
				'from'       => '',
				'to'         => '',
				'account_id' => '',
			] );

			$connection = $query['account_id'];

			if ( is_a( $field_or_connection, Google_Connection::class ) ) {
				$connection = $field_or_connection;
				$field_or_connection = 'cache_key';
			}

			if ( ! is_a( $connection, Google_Connection::class ) ) {
				$connection = new Google_Connection( $query['account_id'], 'account_id' );
			}

			$this->connection    = $connection;

			$query['account_id'] = $connection->get_account_id();

			$this->query        = $query;
			$this->cache_key    = md5( serialize( $query ) );
			$query_or_cache_key = $this->cache_key;
		}

		parent::__construct( $query_or_cache_key, $field_or_connection );
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
		return $this->connection->get_events( $this->query['from'], $this->query['to'] );
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
