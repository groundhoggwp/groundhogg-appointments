<?php
/*
 * Plugin Name: Groundhogg - Booking Calendar
 * Plugin URI:  https://www.groundhogg.io/downloads/booking-calendar/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Description: Create calendars and appointments.
 * Version: 2.5.3
 * Author: Groundhogg Inc.
 * Author URI: https://www.groundhogg.io/?utm_source=wp-plugins&utm_campaign=author-uri&utm_medium=wp-dash
 * Text Domain: groundhogg-calendar
 * Domain Path: /languages
 *
 * Groundhogg is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Groundhogg is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */
namespace GroundhoggBookingCalendar;

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'GROUNDHOGG_BOOKING_CALENDAR_VERSION', '2.5.3' );
define( 'GROUNDHOGG_BOOKING_CALENDAR_PREVIOUS_STABLE_VERSION', '2.5.2' );
define( 'GROUNDHOGG_BOOKING_CALENDAR_NAME', 'Booking Calendar' );

define( 'GROUNDHOGG_BOOKING_CALENDAR__FILE__', __FILE__ );
define( 'GROUNDHOGG_BOOKING_CALENDAR_PLUGIN_BASE', plugin_basename( GROUNDHOGG_BOOKING_CALENDAR__FILE__ ) );
define( 'GROUNDHOGG_BOOKING_CALENDAR_PATH', plugin_dir_path( GROUNDHOGG_BOOKING_CALENDAR__FILE__ ) );

define( 'GROUNDHOGG_BOOKING_CALENDAR_URL', plugins_url( '/', GROUNDHOGG_BOOKING_CALENDAR__FILE__ ) );

define( 'GROUNDHOGG_BOOKING_CALENDAR_ASSETS_PATH', GROUNDHOGG_BOOKING_CALENDAR_PATH . 'assets/' );
define( 'GROUNDHOGG_BOOKING_CALENDAR_ASSETS_URL', GROUNDHOGG_BOOKING_CALENDAR_URL . 'assets/' );
define( 'GROUNDHOGG_BOOKING_CALENDAR_ZOOM_BASE_URL','https://api.zoom.us/v2/' );

define( 'GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_WP_VERSION', '4.9' );
define( 'GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_PHP_VERSION', '7.0' );
define( 'GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_CORE_VERSION', '2.4.6' );

add_action( 'plugins_loaded', function (){
    load_plugin_textdomain( GROUNDHOGG_BOOKING_CALENDAR_TEXT_DOMAIN, false, basename( __DIR__ ) . '/languages' );
} );

define( 'GROUNDHOGG_BOOKING_CALENDAR_TEXT_DOMAIN', 'groundhogg-calendar' );


// Check PHP and WP are up to date!
if ( check_wp_version() && check_php_version() ){

	// Groundhogg is loaded, load now.
	if ( did_action( 'groundhogg/loaded' ) ) {

		if ( check_core_version() ){
			require __DIR__ . '/includes/plugin.php';
		}

		// Lazy load, wait for Groundhogg!
	} else {

		add_action( 'groundhogg/loaded', function () {
			if ( check_core_version() ){
				require __DIR__ . '/includes/plugin.php';
			}
		} );

		// Might not actually be loaded, so we'll check in later.
		check_groundhogg_active();
	}
}

/**
 * Check that Gorundhogg is using the latest available core version
 *
 * @return bool|int
 */
function check_core_version() {

	$correct_version = version_compare( GROUNDHOGG_VERSION, GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_CORE_VERSION, '>=' );

	if ( ! $correct_version ) {
		add_action( 'admin_notices', function () {
			$message      = sprintf( esc_html__( '%s requires Groundhogg version %s+. Because you are using an earlier version, the plugin is currently NOT RUNNING.', 'groundhogg' ), GROUNDHOGG_BOOKING_CALENDAR_NAME, GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_CORE_VERSION );
			$html_message = sprintf( '<div class="notice notice-error">%s</div>', wpautop( $message ) );
			echo wp_kses_post( $html_message );
		} );
	}

	return $correct_version;
}

/**
 * Check that the wp version is most recent
 *
 * @return bool|int
 */
function check_wp_version() {

	$correct_version = version_compare( get_bloginfo( 'version' ), GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_WP_VERSION, '>=' );

	if ( ! $correct_version ) {
		add_action( 'admin_notices', function () {
			$message      = sprintf( esc_html__( '%s requires WordPress version %s+. Because you are using an earlier version, the plugin is currently NOT RUNNING.', 'groundhogg' ), GROUNDHOGG_BOOKING_CALENDAR_NAME, GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_WP_VERSION );
			$html_message = sprintf( '<div class="notice notice-error">%s</div>', wpautop( $message ) );
			echo wp_kses_post( $html_message );
		} );
	}

	return $correct_version;
}

/**
 * Check that the PHP version is compatible
 *
 * @return bool|int
 */
function check_php_version() {

	$correct_version = version_compare( PHP_VERSION, GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_PHP_VERSION, '>=' );

	if ( ! $correct_version ) {
		add_action( 'admin_notices', function () {
			$message      = sprintf( esc_html__( '%s requires PHP version %s+, plugin is currently NOT RUNNING.', 'groundhogg' ), GROUNDHOGG_BOOKING_CALENDAR_NAME, GROUNDHOGG_BOOKING_CALENDAR_REQUIRED_PHP_VERSION );
			$html_message = sprintf( '<div class="notice notice-error">%s</div>', wpautop( $message ) );
			echo wp_kses_post( $html_message );
		} );
	}

	return $correct_version;
}

/**
 * Check that Groundhogg is active!
 */
function check_groundhogg_active() {
	// Might not actually be loaded, so we'll check in later.
	add_action( 'admin_notices', function () {

		// Is not loaded!
		if ( ! defined( 'GROUNDHOGG_VERSION' ) ) {
			$message      = sprintf( esc_html__( 'Groundhogg is not currently active, it must be active for %s to work.', 'groundhogg' ), GROUNDHOGG_BOOKING_CALENDAR_NAME );
			$html_message = sprintf( '<div class="notice notice-warning">%s</div>', wpautop( $message ) );
			echo wp_kses_post( $html_message );
		}
	} );
}