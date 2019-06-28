<?php
namespace GroundhoggBookingCalendar\Api;

use Groundhogg\Api\V3\Base;

use GroundhoggBookingCalendar\Classes\Calendar;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Calendar_Api extends Base
{

    public function register_routes()
    {
        register_rest_route(self::NAME_SPACE, '/calendar/slots', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_slots' ],
                'args' => [
                    'id' => [
                        'description' => _x( 'Id of the calendar.', 'api', 'groundhogg' )
                    ],
                    'date' => [
                        'required'    => false,
                        'description' => _x( 'Date to query.', 'api', 'groundhogg' ),
                    ],
                ]
            ]
        ] );
    }

    public function get_slots(WP_REST_Request $request)
    {

//        if ( ! current_user_can( 'add_appointments' ) ) {
//            wp_send_json_error();
//        }

        $ID = absint( $request->get_param( 'id' ) );

        $calendar = new Calendar( $ID );

        if ( ! $calendar->exists() ){
            wp_send_json_error();
        }

        $date = $request->get_param( 'date' );

        $slots = $calendar->get_appointment_slots( $date );

        if ( empty( $slots ) ){
            wp_send_json_error( __( 'No slots available.' ) );
        }

        wp_send_json_success( [ 'slots' => $slots ] );
    }
}