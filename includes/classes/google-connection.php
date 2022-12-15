<?php

namespace GroundhoggBookingCalendar\Classes;

use Google\Service\Exception;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Oauth2;
use Groundhogg\Base_Object;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\DB\Google_Connections;
use function Groundhogg\get_array_var;
use function Groundhogg\get_date_time_format;
use function Groundhogg\get_db;
use function Groundhogg\get_time;
use function Groundhogg\isset_not_empty;
use function GroundhoggBookingCalendar\get_max_booking_period;

class Google_Connection extends Base_Object {

	protected $client;

	/**
	 * Create a new connection from an Auth code.
	 *
	 * @param $code
	 */
	public function create_from_auth( $code ) {

		$response = Plugin::instance()->proxy_service->request( 'authentication/get', [
			'code' => $code,
			'slug' => 'google'
		] );

		if ( is_wp_error( $response ) ) {
			$this->add_error( $response, $this );

			return;
		}

		$data = (array) get_array_var( $response, 'token' );

		if ( ! $data ) {
			$this->add_error( new \WP_Error( 'failed', 'invalid token', $this ) );

			return;
		}

		$this->setup_client( $data );

		$account_info = $this->get_account_info();

		$data = array_merge( $data, [
			'account_id'    => $account_info->id,
			'account_email' => $account_info->email,
		] );

		$obj = $this->get_from_db( 'account_email', $data['account_email'] );

		if ( $obj ) {
			$this->setup_object( $obj );
			$this->update( (array) $data );
		} else {
			$this->create( (array) $data );
		}

		if ( ! $this->exists() ) {
			$this->add_error( 'error', 'Could not setup object.', $this );

			return;
		}

		$this->sync_calendars();
	}

	/**
	 * Refresh the token when required
	 */
	public function refresh() {

		$response = Plugin::instance()->proxy_service->request( 'authentication/refresh', [
			'token' => $this->data,
			'slug'  => 'google'
		] );

		if ( is_wp_error( $response ) ) {
			$this->add_error( $response, $this );

			return;
		}

		$data = (array) get_array_var( $response, 'token' );

		if ( ! $data || ! isset_not_empty( $data, 'access_token' ) ) {
			$this->add_error( new \WP_Error( 'failed', 'invalid token', $this ) );
			$this->update( [
				'status' => 'inactive'
			] );

			return;
		}

		$data['status'] = 'active';

		$this->update( $data );
	}

	/**
	 * Setup the google client
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setup_client( $token = false ) {

		if ( $this->client ) {
			return;
		}

		try {

			$client = new Google_Client();

			$guzzleClient = new \GuzzleHttp\Client( array( 'curl' => array( CURLOPT_SSL_VERIFYPEER => false ) ) );

			$client->setHttpClient( $guzzleClient );

			$client->setAccessToken( $token ?: $this->data );

			if ( $client->isAccessTokenExpired() && $client->getRefreshToken() ) {

				$this->refresh();

				if ( ! $this->has_errors() ) {
					$client->setAccessToken( $this->data );
				}

			}

		} catch ( \Exception $e ) {
			$this->add_error( $e->getCode(), $e->getMessage() );

			return;
		}

		$this->client = $client;
	}

	/**
	 * Get the google client
	 *
	 * @return Google_Client
	 */
	public function get_client() {
		if ( ! $this->client ) {
			$this->setup_client();
		}

		return $this->client;
	}

	/**
	 * Syncs the calendars and adds the to the tables
	 */
	public function sync_calendars() {

		$client = $this->get_client();

		// Errors setting up the Client
		if ( $this->has_errors() ) {
			return;
		}

		if ( ! is_a( $client, 'Google_Client' ) ) {
			return;
		}

		$service = new Google_Service_Calendar( $client );

		$synced_calendars = [];

		try {
			$calendarList = $service->calendarList->listCalendarList();
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

		do {

			foreach ( $calendarList->getItems() as $calendarListEntry ) {
				$synced_calendars[] = [
					'id'   => $calendarListEntry->getId(),
					'name' => $calendarListEntry->getSummary()
				];
			}

			$pageToken = $calendarList->getNextPageToken();

			if ( $pageToken ) {
				$optParams    = array( 'pageToken' => $pageToken );
				$calendarList = $service->calendarList->listCalendarList( $optParams );
			}
		} while ( $pageToken );

		$this->update( [
			'all_calendars' => $synced_calendars
		] );

	}

	/**
	 * List of associated google calendars!
	 *
	 * @return array[]
	 */
	public function get_calendars() {
		return $this->all_calendars;
	}

	/**
	 * Get the account info
	 */
	protected function get_account_info() {
		$service = new Google_Service_Oauth2( $this->get_client() );

		return $service->userinfo->get();
	}

	/**
	 * Retrieves the account ID
	 *
	 * @return bool|mixed
	 */
	public function get_account_id() {
		return $this->account_id;
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

		$client = $this->get_client();

		// Errors setting up the Client
		if ( $this->has_errors() ) {
			return [];
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

		foreach ( $this->check_for_conflicts as $calendar_id ){
			do {

				try {
					$_events = $service->events->listEvents( $calendar_id, $optParams );
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
		}

		return $events;
	}

	/**
	 * Get cached events for this connection
	 *
	 * @param $from
	 * @param $to
	 *
	 * @return array
	 */
	public function get_cached_events( $from, $to ) {

		$query = new Synced_Event_Query( [
			'from'    => $from,
			'to'      => $to,
		], $this );

		return $query->get_results();
	}

	/**
	 * Handle setup actions
	 */
	protected function post_setup() {

		$this->expires_in          = absint( $this->expires_in );
		$this->created             = absint( $this->created );
		$this->all_calendars       = maybe_unserialize( $this->all_calendars );
		$this->check_for_conflicts = array_filter( explode( ',', $this->check_for_conflicts ) );

		$this->setup_client();
	}

	/**
	 * @return bool|mixed
	 */
	public function get_main_calendar_id(){
		return $this->add_appointments_to;
	}

	/**
	 * Get the DB
	 *
	 * @return Google_Connections
	 */
	protected function get_db() {
		return get_db( 'google_connections' );
	}
//
//	/**
//	 * @return array
//	 */
//	public function get_as_array() {
//		return [
//			'ID'            => $this->get_id(),
//			'user_id'       => $this->user_id,
//			'account_id'    => $this->account_id,
//			'account_email' => $this->account_email,
//			'calendars'     => $this->get_calendars(),
//		];
//	}

	protected function sanitize_columns( $data = [] ) {

		foreach ( $data as $column => &$val ) {
			switch ( $column ) {
				case 'all_calendars':
					$val = maybe_serialize( $val );
					break;
				case 'check_for_conflicts':
					$val = is_array( $val ) ? implode(',', $val ) : $val;
					break;
			}
		}

		return $data;
	}

}
