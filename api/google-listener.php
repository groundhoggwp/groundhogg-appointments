<?php

namespace GroundhoggBookingCalendar\Api;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Groundhogg\Api\V3\Base;
use WP_REST_Server;
use WP_REST_Request;

class Google_Listener extends Base {

	public function register_routes() {
		register_rest_route( self::NAME_SPACE, '/google/listen', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'permission_callback' => [ $this, 'verify_token' ],
				'callback'            => [ $this, 'listen' ],
			]
		] );
	}

	/**
	 * Verify the token is correct
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool
	 */
	function verify_token( WP_REST_Request $request ) {
		return $request->get_header( 'x-goog-channel-token' ) === get_option( 'gh_google_webhook_token' ) ;
	}

	/**
	 * Handle the request from Google
	 *
	 * @param WP_REST_Request $request
	 */
	public function listen( WP_REST_Request $request ){
		$channel_id  = $request->get_header( 'x-goog-channel-id' );
		$resource_id = $request->get_header( 'x-goog-resource-id' );

		// Check if the channel exits

		// Fetch the event from the api
	}
}