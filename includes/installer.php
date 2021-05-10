<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Base_Object;
use GroundhoggBookingCalendar\Classes\Calendar;
use function Groundhogg\get_db;
use function Groundhogg\install_custom_rewrites;
use function Groundhogg\words_to_key;

class Installer extends \Groundhogg\Installer {

	protected function activate() {

		install_tables();

		Plugin::$instance->roles->install_roles_and_caps();

		// Create the default calendars
		if ( get_db( 'calendars' )->count() === 0 ) {
			$c15 = new Calendar( [
				'user_id'     => get_current_user_id(),
				'name'        => __( '15 Minutes', 'groundhogg-calendar' ),
				'description' => __( "Let's chat for 15 minutes.", 'groundhogg-calendar' ),
				'slug'        => '15-minutes'
			] );

			set_calendar_default_settings( $c15, 0, 15 );

			$c30 = new Calendar( [
				'user_id'     => get_current_user_id(),
				'name'        => __( '30 Minutes', 'groundhogg-calendar' ),
				'description' => __( "Let's chat for 30 minutes.", 'groundhogg-calendar' ),
				'slug'        => '30-minutes'
			] );

			set_calendar_default_settings( $c30, 0, 30 );

			$c60 = new Calendar( [
				'user_id'     => get_current_user_id(),
				'name'        => __( '1 Hour', 'groundhogg-calendar' ),
				'description' => __( "Let's chat for 1 hour.", 'groundhogg-calendar' ),
				'slug'        => '1-hour'
			] );

			set_calendar_default_settings( $c60, 1, 0 );
		}

		install_custom_rewrites();
	}

	protected function deactivate() {
		// TODO: Implement deactivate() method.
	}

	/**
	 * The path to the main plugin file
	 *
	 * @return string
	 */
	function get_plugin_file() {
		return GROUNDHOGG_BOOKING_CALENDAR__FILE__;
	}

	/**
	 * Get the plugin version
	 *
	 * @return string
	 */
	function get_plugin_version() {
		return GROUNDHOGG_BOOKING_CALENDAR_VERSION;
	}

	/**
	 * A unique name for the updater to avoid conflicts
	 *
	 * @return string
	 */
	protected function get_installer_name() {
		return words_to_key( GROUNDHOGG_BOOKING_CALENDAR_NAME );
	}
}