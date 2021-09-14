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
use function Groundhogg\isset_not_empty;

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

			return;
		}

		$this->update( $data );
	}

	/**
	 * Setup the google client
	 */
	public function setup_client( $token = false ) {

		if ( $this->client ) {
			return;
		}

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

		$service      = new Google_Service_Calendar( $client );

		try {
			$calendarList = $service->calendarList->listCalendarList();
		} catch ( Exception $e ){
			$this->add_error( $e->getCode(), $e->getMessage() );
			return;
		}

		do {

			foreach ( $calendarList->getItems() as $calendarListEntry ) {

				$cal = new Google_Calendar( [
					'google_calendar_id' => $calendarListEntry->getId(),
					'google_account_id'  => $this->get_account_id(),
					'connection_id'      => $this->get_id(),
				], null, $this );

				$cal->update( [
					'name' => $calendarListEntry->getSummary()
				] );
			}

			$pageToken = $calendarList->getNextPageToken();

			if ( $pageToken ) {
				$optParams    = array( 'pageToken' => $pageToken );
				$calendarList = $service->calendarList->listCalendarList( $optParams );
			}
		} while ( $pageToken );

	}

	protected $calendars = [];

	/**
	 * List of associated google calendars!
	 *
	 * @return Google_Calendar[]
	 */
	public function get_calendars() {

		$self = $this;

		if ( empty( $this->calendars ) ) {

			$calendars = get_db( 'google_calendars' )->query( [
				'account_id'    => $this->get_account_id(),
				'connection_id' => $this->get_id(),
			] );

			$this->calendars = array_map( function ( $cal ) use ( $self ) {
				return new Google_Calendar( $cal, null, $self );
			}, $calendars );

		}

		return $this->calendars;
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
	 * Handle setup actions
	 */
	protected function post_setup() {

		$this->expires_in = absint( $this->expires_in );
		$this->created    = absint( $this->created );

		$this->setup_client();
	}

	/**
	 * Get the DB
	 *
	 * @return Google_Connections
	 */
	protected function get_db() {
		return get_db( 'google_connections' );
	}

}
