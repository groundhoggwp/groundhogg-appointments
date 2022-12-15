<?php

namespace GroundhoggBookingCalendar\Connections;

use Groundhogg\Plugin;
use function Groundhogg\array_map_with_keys;
use function Groundhogg\get_array_var;
use function Groundhogg\remote_post_json;

class Zoom extends Base {

	public function slug() {
		return 'zoom';
	}

	public function endpoint_base() {
		return GROUNDHOGG_BOOKING_CALENDAR_ZOOM_BASE_URL;
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

		$result = remote_post_json( $this->endpoint_base() . $endpoint, $body, $method, [
			'Authorization' => 'Bearer ' . $token,
		] );

		if ( $result->code >= 300 ) {
			return new \WP_Error( 'error', $result->message );
		}

		return $result;
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

		$user = $this->request( $token_details->access_token, 'users/me', '', 'GET' );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$token_details->email = $user->email;
		$account_id           = $user->id;

		if ( ! $token_details ) {
			return new \WP_Error( 'failed', $response->get_error_message() );
		}

		return $this->add_connection( $token_details, $account_id );
	}
}
