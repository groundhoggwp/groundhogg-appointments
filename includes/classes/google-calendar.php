<?php

namespace GroundhoggBookingCalendar\Classes;

use Google_Service_Calendar;
use Groundhogg\Base_Object;
use Groundhogg\DB\DB;
use Groundhogg\Event;
use function Groundhogg\get_db;
use function GroundhoggBookingCalendar\get_max_booking_period;

class Google_Calendar extends Base_Object {

	protected $connection;

	public function __construct( $identifier_or_args = 0, $field = null, $connection = null ) {
		parent::__construct( $identifier_or_args, $field );

		$this->connection = $connection;
	}

	protected function post_setup() {
		$numeric_keys = [
			'google_account_id',
			'connection_id',
			'last_synced',
		];

		foreach ( $numeric_keys as $key ) {
			$this->$key = intval( $this->$key );
		}
	}

	protected function get_db() {
		return get_db( 'google_calendars' );
	}

	/**
	 * @return Google_Connection
	 */
	public function get_connection() {

		if ( ! $this->connection ) {
			$this->connection = new Google_Connection( $this->connection_id );
		}

		return $this->connection;
	}

	/**
	 * @return bool
	 */
	public function is_sync_enabled() {
		return $this->sync_status === 'on';
	}

	/**
	 * Enable calendar sync
	 */
	public function enable_sync() {
		$this->update( [
			'sync_status' => 'on'
		] );
	}

	/**
	 * Disable calendar sync
	 */
	public function disable_sync() {
		$this->update( [
			'sync_status' => 'off'
		] );
	}

	/**
	 * Sync all of the events from the Google Calendar to the synced events table
	 */
	public function sync_events() {

		$this->update( [ 'last_synced' => time() ] );

		$client = $this->get_connection()->get_client();

		$service = new Google_Service_Calendar( $client );

		//check for the calendar
		$optParams = array(
			'orderBy'      => 'startTime',
			'singleEvents' => true,
			'timeMin'      => date( DATE_RFC3339 ),
			'timeMax'      => date( DATE_RFC3339, get_max_booking_period() ),
			'timeZone'     => 'UTC'
		);

		do {

			$events = $service->events->listEvents( $this->google_calendar_id, $optParams );

			foreach ( $events->getItems() as $event ) {

				$synced = new Synced_Event( $event->getId(), 'event_id' );

				if ( ! $synced->exists() ) {
					$synced->create_from_event( $event, $this );
				} else {
					$synced->update_from_event( $event );
				}
			}

			$pageToken = $events->getNextPageToken();

			if ( $pageToken ) {
				$optParams['pageToken'] = $pageToken;
			}

		} while ( $pageToken );
	}
}
