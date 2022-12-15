<?php

namespace GroundhoggBookingCalendar\Classes;

use Google\Service\Exception;
use Google_Service_Calendar;
use Groundhogg\Base_Object;
use Groundhogg\DB\DB;
use Groundhogg\Event;
use function Groundhogg\get_db;
use function Groundhogg\get_time;
use function GroundhoggBookingCalendar\get_max_booking_period;

class Google_Calendar extends Base_Object {

	/**
	 * @var Google_Connection
	 */
	protected $connection;

	public function __construct( $identifier_or_args = 0, $field = null, $connection = null ) {
		parent::__construct( $identifier_or_args, $field );

		$this->connection = $connection;
	}

	public function get_remote_google_id() {
		return $this->google_calendar_id;
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
	 * Get events from google calendar
	 *
	 *
	 * @throws \Exception
	 *
	 * @param $to
	 * @param $from
	 *
	 * @return array|false
	 */
	public function get_events( $from = false, $to = false ){

		$events = [];

		if ( ! $from ) {
			$from = time();
		}

		if ( ! $to ) {
			$to = get_max_booking_period();
		}

		$this->update( [ 'last_synced' => time() ] );

		$client = $this->get_connection()->get_client();

		// Errors setting up the Client
		if ( $this->connection->has_errors() ) {

			foreach ( $this->connection->get_errors() as $error ) {
				$this->add_error( $error );
			}

			return false;
		}

		$service = new Google_Service_Calendar( $client );

		//check for the calendar
		$optParams = array(
			'orderBy'      => 'startTime',
			'singleEvents' => true, // change
			'timeMin'      => date( DATE_RFC3339, get_time( $from ) ),
			'timeMax'      => date( DATE_RFC3339, get_time( $to ) ),
			'timeZone'     => 'UTC'
		);

		do {

			try {
				$_events = $service->events->listEvents( $this->google_calendar_id, $optParams );
			} catch ( Exception $e ) {
				$this->add_error( $e->getCode(), $e->getMessage() );

				switch ( $e->getCode() ) {
					case 'code_invalid':
					case 'invalid_grant':
						$this->update( [
							'status' => 'inactive'
						] );
						break;
				}

				return false;
			}

			foreach ( $_events->getItems() as $event ) {
				$events[] = new Synced_Event( $event );
			}

			$pageToken = $_events->getNextPageToken();

			if ( $pageToken ) {
				$optParams['pageToken'] = $pageToken;
			}

		} while ( $pageToken );

		return $events;
	}


	/**
	 * Sync all of the events from the Google Calendar to the synced events table
	 */
	public function sync_events( $from = false, $to = false ) {

		if ( ! $from ) {
			$from = time();
		}

		if ( ! $to ) {
			$to = get_max_booking_period();
		}

		$this->update( [ 'last_synced' => time() ] );

		$client = $this->get_connection()->get_client();

		// Errors setting up the Client
		if ( $this->connection->has_errors() ) {

			foreach ( $this->connection->get_errors() as $error ) {
				$this->add_error( $error );
			}

			return;
		}

		$service = new Google_Service_Calendar( $client );

		//check for the calendar
		$optParams = array(
			'orderBy'      => 'startTime',
			'singleEvents' => true, // change
			'timeMin'      => date( DATE_RFC3339, get_time( $from ) ),
			'timeMax'      => date( DATE_RFC3339, get_time( $to ) ),
			'timeZone'     => 'UTC'
		);

		do {

			try {
				$events = $service->events->listEvents( $this->google_calendar_id, $optParams );
			} catch ( Exception $e ) {
				$this->add_error( $e->getCode(), $e->getMessage() );

				switch ( $e->getCode() ) {
					case 'code_invalid':
					case 'invalid_grant':
						$this->update( [
							'status' => 'inactive'
						] );
						break;
				}

				return;
			}

			$pageToken = $events->getNextPageToken();

			if ( $pageToken ) {
				$optParams['pageToken'] = $pageToken;
			}

		} while ( $pageToken );
	}
}
