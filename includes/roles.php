<?php

namespace GroundhoggBookingCalendar;

use GroundhoggBookingCalendar\Classes\Appointment;

class Roles extends \Groundhogg\Roles {

	/**
	 * Returns an array  of role => [
	 *  'role' => '',
	 *  'name' => '',
	 *  'caps' => []
	 * ]
	 *
	 * In this case caps should just be the meta cap map for other WP related stuff.
	 *
	 * @return array[]
	 */
	public function get_roles() {
		return [];
	}

	/**
	 * Map caps to primitives
	 *
	 * @param array  $caps
	 * @param string $cap
	 * @param int    $user_id
	 * @param array  $args
	 *
	 * @return array
	 */
	public function map_meta_cap( $caps, $cap, $user_id, $args ) {

		switch ( $cap ) {
			case 'edit_appointment':
			case 'view_appointment':
			case 'delete_appointment':

				$caps = [];

				$parts = explode( '_', $cap );

				$caps[] = $parts[0] . '_appointments';

				if ( is_a( $args[0], Appointment::class ) ){
					$appt = $args[0];
				} else {
					$appt = new Appointment( $args[0] );
				}

				// Not a deal
				if ( ! $appt->exists() ){
					$caps[] = 'do_not_allow';
					break;
				}

				// User is not the owner of the deal
				if ( $appt->get_owner_id() !== $user_id ){
					$caps[] = $parts[0] . '_others_appointments';
				}

				break;
		}

		return $caps;
	}

	public function get_administrator_caps() {
		return [
			'add_appointments',
			'delete_appointments',
			'delete_others_appointments',
			'edit_appointments',
			'edit_others_appointments',
			'view_appointments',
			'view_others_appointments',

			'add_calendars',
			'delete_calendars',
			'edit_calendars',
			'view_calendars',
		];
	}

	public function get_marketer_caps() {
		return [
			'add_appointments',
			'delete_appointments',
			'delete_others_appointments',
			'edit_appointments',
			'edit_others_appointments',
			'view_appointments',
			'view_others_appointments',

			'add_calendars',
			'delete_calendars',
			'edit_calendars',
			'view_calendars',
		];
	}

	public function get_sales_manager_caps() {
		return [
			'add_appointments',
			'view_appointments',
			'view_others_appointments',
			'delete_appointments',
			'delete_others_appointments',
			'edit_appointments',
			'edit_others_appointments',

			'edit_calendars',
			'view_calendars',
		];

	}

	public function get_sales_rep_caps() {
		return [
			'view_appointments',
			'add_appointments',
			'delete_appointments',
			'edit_appointments',

			'edit_calendars',
		];
	}


	/**
	 * Return a cap to check against the admin to ensure caps are also installed.
	 *
	 * @return mixed
	 */
	protected function get_admin_cap_check() {
		return 'add_calendars';
	}
}
