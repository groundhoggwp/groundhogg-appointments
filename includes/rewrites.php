<?php

namespace GroundhoggBookingCalendar;

use GroundhoggBookingCalendar\Classes\Appointment;
use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\add_managed_rewrite_rule;
use function Groundhogg\get_request_var;
use function Groundhogg\is_managed_page;
use Groundhogg\Utils\Abstract_Rewrites;

class Rewrites extends Abstract_Rewrites {

	public function get_template_loader() {
		return new Template_Loader();
	}

	/**
	 * Add the rules
	 */
	public function add_rewrite_rules() {
		add_managed_rewrite_rule( 'calendar/([^/?]*)/?$', 'subpage=calendar&calendar_id=$matches[1]', 'top' );
		add_managed_rewrite_rule( 'calendar/([^/?]*)/hosted/?$', 'subpage=calendar_hosted&calendar_id=$matches[1]', 'top' );
		add_managed_rewrite_rule( 'appointment/([^/]*)/([^/]*)/?$', 'subpage=appointment&appointment_id=$matches[1]&action=$matches[2]', 'top' );
	}

	/**
	 * @param $vars
	 *
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'subpage';
		$vars[] = 'action';
		$vars[] = 'calendar_id';
		$vars[] = 'appointment_id';

		return $vars;
	}

	/**
	 * @param $query
	 *
	 * @return mixed
	 */
	public function parse_query( $query ) {
//		$this->map_query_var( $query, 'calendar_id', 'absint' );

		// Appointment ID is encrypted for security!
		$this->map_query_var( $query, 'appointment_id', 'urldecode' );
		$this->map_query_var( $query, 'appointment_id', '\Groundhogg\decrypt' );
		$this->map_query_var( $query, 'appointment_id', 'absint' );

		return $query;
	}

	/**
	 * @param string $template
	 *
	 * @return string
	 */
	public function template_include( $template = '' ) {

		if ( ! is_managed_page() ) {
			return $template;
		}

		$page           = get_query_var( 'subpage' );
		$calendar_id    = get_query_var( 'calendar_id' );
		$appointment_id = get_query_var( 'appointment_id' );

		if ( $appointment_id ) {

			$appointment = new Appointment( $appointment_id );

			if ( ! $appointment->exists() ) {
				return $template;
			}

			$calendar = $appointment->get_calendar();

			set_query_var( 'appointment', $appointment );

		} else {

			if ( ! is_numeric( $calendar_id ) ) {
				$calendar_id = [ 'slug' => $calendar_id ];
			}

			$calendar = new Calendar( $calendar_id );

			if ( ! $calendar->exists() ) {
				return $template;
			}

		}

		set_query_var( 'calendar', $calendar );

		$template_loader = $this->get_template_loader();

		switch ( $page ) {
			case 'calendar':
			case 'calendar_hosted':
			case 'appointment':
				$template = $template_loader->get_template_part( 'calendar', '', false );
				break;
		}

		return $template;
	}

	public function template_redirect( $template = '' ) {
		// TODO: Implement template_redirect() method.
	}
}