<?php
namespace GroundhoggBookingCalendar\Api;

use Groundhogg\Api\V3;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use Groundhogg\Api\V3\Base;
use Groundhogg\Plugin;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Calendar_Api extends Base
{

    public function register_routes()
    {

        $auth_callback = $this->get_auth_callback();

        register_rest_route(self::NAME_SPACE, '/calendar', [
            [
	            'methods' => WP_REST_Server::READABLE,
	            'callback' => [ $this, 'get_calendar' ],
	            'permission_callback' => $auth_callback,

            ],  [
		        'methods'             => WP_REST_Server::CREATABLE,
		        'callback'            => [ $this, 'add_calendar' ],
		        'permission_callback' => $auth_callback,

	        ],
	        [
		        'methods'             => WP_REST_Server::DELETABLE,
		        'callback'            => [ $this, 'delete_calendar' ],
		        'permission_callback' => $auth_callback,

	        ],
	        [
		        'methods'             => WP_REST_Server::EDITABLE,
		        'callback'            => [ $this, 'edit_calendar' ],
		        'permission_callback' => $auth_callback,

	        ]


        ] );



    }

    /**
     * Get a list of calendar
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function get_calendar( WP_REST_Request $request )
    {

	    return self::SUCCESS_RESPONSE( [ 'success' ] );
    }

	/**
	 * Creates a new calendar
	 *
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function add_calendar( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE();
	}

	/**
	 *  Deletes the calendar and relevant data
	 *
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function delete_calendar( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE();
	}

	/**
	 * update the calendar
	 *
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function edit_calendar( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE();
	}






}