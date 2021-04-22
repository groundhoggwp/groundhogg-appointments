<?php

namespace GroundhoggBookingCalendar\Connections;


class Google extends Base {

	public function slug() {
		return 'google';
	}

	public function endpoint_base() {
		return '';
	}

	/**
	 * In dhrumit's code the entire Access Token response is used for most things.
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

		return $connection;
	}
}
