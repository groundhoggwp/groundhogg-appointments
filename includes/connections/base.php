<?php

namespace GroundhoggBookingCalendar\Connections;

use Groundhogg\Plugin;
use function Groundhogg\array_map_with_keys;
use function Groundhogg\get_array_var;
use function Groundhogg\remote_post_json;

abstract class Base {

	protected $connections = [];

	/**
	 * @return string
	 */
	abstract public function slug();

	abstract public function endpoint_base();

	public function __construct() {
		$this->connections = get_option( "gh_{$this->slug()}_connections", [] );
	}

	public function save() {
		update_option( "gh_{$this->slug()}_connections", $this->connections );
	}

	/**
	 * Send a request
	 *
	 * @param        $token_or_account_id
	 * @param        $endpoint
	 * @param        $body
	 * @param string $method
	 *
	 * @return array|bool|object|\WP_Error
	 */
	public function request( $token_or_account_id, $endpoint, $body, $method = 'POST' ) {

		$token = $this->get_access_token( $token_or_account_id ) ?: $token_or_account_id;

		return remote_post_json( $this->endpoint_base() . $endpoint, $body, $method, [
			'Authorization' => 'Bearer ' . $token,
		] );
	}

	/**
	 * Adds the new connection to the overall google connections
	 *
	 * @param $details
	 *
	 * @return string the access token
	 */
	public function add_connection( $details, $account_id = false ) {

		// cast to array
		$details = (array) $details;

		$account_id = $account_id ?: get_current_user_id();

		$existing_details = $this->get_connection_details( $account_id );

		if ( $existing_details ){
			$details = wp_parse_args( $details, $existing_details );
		}

		if ( ! isset( $details['created'] ) ) {
			$details['created'] = time() - MINUTE_IN_SECONDS;
		}

		$this->connections[ $account_id ] = $details;

		$this->save();

		return $account_id;
	}

	/**
	 * Get all the connections
	 *
	 * @return array
	 */
	public function get_connections() {
		return $this->connections;
	}

	/**
	 * Get the connections as a dropdown friendly array
	 *
	 * @return array
	 */
	public function get_connections_for_dropdown() {
		return array_map_with_keys( $this->connections, function ( $details, $id ) {
			return get_userdata( $id )->user_email;
		} );
	}

	/**
	 * Get the relevant access token
	 *
	 * @param $id
	 *
	 * @return bool|mixed|\WP_Error
	 */
	public function get_access_token( $id ) {
		$connection = $this->get_connection_details( $id );

		if ( ! $connection ) {
			return new \WP_Error( 'error', 'no token' );
		}

		return get_array_var( $connection, 'access_token' );
	}

	/**
	 * Refresh an existing connection
	 *
	 * @param $id
	 *
	 * @return string|\WP_Error
	 */
	public function refresh_connection( $id ) {
		$connection = $this->get_connection_details( $id );

		if ( ! $connection ) {
			return new \WP_Error( 'error', 'no token' );
		}

		$refresh_token = $this->get_refresh_token( $id, $connection );

		$response = Plugin::instance()->proxy_service->request( 'authentication/refresh', [
			'token' => $refresh_token,
			'slug'  => $this->slug()
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'failed', $response->get_error_message() );
		}

		$token_details = get_array_var( $response, 'token' );

		if ( ! $token_details ) {
			return new \WP_Error( 'failed', $response->get_error_message() );
		}

		return $this->add_connection( $token_details, $id );
	}

	/**
	 * Given an auth code establish a new google connection!
	 *
	 * @param $auth
	 *
	 * @return string|\WP_Error
	 */
	public function init_connection( $auth ) {

		$response = Plugin::instance()->proxy_service->request( 'authentication/get', [
			'code' => $auth,
			'slug' => $this->slug()
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'failed', $response->get_error_message() );
		}

		$token_details = get_array_var( $response, 'token' );

		if ( ! $token_details ) {
			return new \WP_Error( 'failed', $response->get_error_message() );
		}

		return $this->add_connection( $token_details );
	}

	/**
	 * Get the details for a specific token
	 *
	 * @param $id
	 *
	 * @return bool|mixed
	 */
	public function get_connection_details( $id ) {
		return get_array_var( $this->connections, $id );
	}

	/**
	 * Check if the given token is expired
	 * It's expired if the current time is greater than the expires time
	 *
	 * @param $id
	 *
	 * @return bool|\WP_Error
	 */
	public function is_token_expired( $id ) {
		$connection = $this->get_connection_details( $id );

		if ( ! $connection ) {
			return new \WP_Error( 'error', 'no token' );
		}

		return time() > ( $connection['created'] + $connection['expires_in'] );
	}

	/**
	 * @param $id
	 *
	 * @return bool|mixed
	 */
	protected function get_refresh_token( $id, $connection ) {
		return get_array_var( $connection, 'refresh_token', $connection );
	}
}
