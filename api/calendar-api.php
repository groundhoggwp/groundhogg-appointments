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

        register_rest_route(self::NAME_SPACE, '/calendars', [
            [
	            'methods' => WP_REST_Server::READABLE,
	            'callback' => [ $this, 'get_calendars' ],
	            'permission_callback' => $auth_callback,

            ]
        ] );

        register_rest_route(self::NAME_SPACE, '/appointments' ,array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'get_appointments' ],
            'permission_callback' => $auth_callback,
        ));

    }

	public function get_calendars( WP_REST_Request $request ) {

		return self::SUCCESS_RESPONSE( ["calendars" => [ [1,2,3,4] ] ]  );
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_appointments( WP_REST_Request $request ) {

		// fetch the calendar ID

		return self::SUCCESS_RESPONSE( ["appointments" => [ [1,2,3,4] ] ]  );


	}




	}