<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Install
 *
 * Runs on plugin install by setting up the post types, custom taxonomies,
 * flushing rewrite rules to initiate the new 'downloads' slug and also
 * creates the plugin and populates the settings fields for those plugin
 * pages. After successful install, the user is redirected to the WPGH Welcome
 * screen.
 *
 * @since 1.0
 * @global $wpdb
 * @global $wpgh_options
 * @param  bool $network_wide If the plugin is being network-activated
 * @return void
 */
function wpgh_appt_install( $network_wide = false ) {
    global $wpdb;

    if ( is_multisite() && $network_wide ) {

        foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs LIMIT 100" ) as $blog_id ) {
            switch_to_blog( $blog_id );
            wpgh_appt_activate();
            restore_current_blog();
        }
    } else {
        wpgh_appt_activate();
    }
    file_put_contents( __DIR__ . '/my_log.html', ob_get_contents() );
}

register_activation_hook( WPGH_APPOINTMENT_PLUGIN_FILE, 'wpgh_appt_install' );

/**
 * Convert customers into contacts.
 */
function wpgh_appt_activate()
{

    //create database

    WPGH_APPOINTMENTS()->appointments->create_table();
    WPGH_APPOINTMENTS()->appointmentmeta->create_table();
    WPGH_APPOINTMENTS()->calendarmeta->create_table();
    WPGH_APPOINTMENTS()->calendar->create_table();

    //install stuff goes here.

    $roles = new WPGH_Roles_Calendar();
    $roles->add_caps();



}


