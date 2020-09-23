<?php

namespace GroundhoggBookingappointment\Api;

use Groundhogg\Api\V3;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Groundhogg\Api\V3\Base;
use Groundhogg\Plugin;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Appointment_Api extends Base {

	public function register_routes() {

		$auth_callback = $this->get_auth_callback();

		register_rest_route( self::NAME_SPACE, '/appointment', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_appointment' ],
				'permission_callback' => $auth_callback,

			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_appointment' ],
				'permission_callback' => $auth_callback,

			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_appointment' ],
				'permission_callback' => $auth_callback,

			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'edit_appointment' ],
				'permission_callback' => $auth_callback,

			]


		] );


	}

	/**
	 * Get a list of appointment
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_appointment( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE( [ 'success' ] );
	}

	/**
	 * Creates a new appointment
	 *
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function add_appointment( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE();
	}

	/**
	 *  Deletes the appointment and relevant data
	 *
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function delete_appointment( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE();
	}

	/**
	 * update the appointment
	 *
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function edit_appointment( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE();
	}


}