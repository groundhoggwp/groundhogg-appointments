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
 * @param bool $network_wide
 */
function wpgh_appt_install( $network_wide = false ) {

    global $wpdb;

    if ( is_multisite() && $network_wide ) {

        foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs LIMIT 100" ) as $blog_id ) {
            switch_to_blog( $blog_id );

            echo $blog_id . '<br/>';

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

/**
 * When a new Blog is created in multisite, see if WPGH is network activated, and run the installer
 *
 * @since  2.5
 * @param  int    $blog_id The Blog ID created
 * @param  int    $user_id The User ID set as the admin
 * @param  string $domain  The URL
 * @param  string $path    Site Path
 * @param  int    $site_id The Site ID
 * @param  array  $meta    Blog Meta
 * @return void
 */
function wpgh_appt_new_blog_created( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {

    if ( is_plugin_active_for_network( plugin_basename( WPGH_PLUGIN_FILE ) ) ) {

        switch_to_blog( $blog_id );
        wpgh_appt_activate();
        restore_current_blog();

    }

}

add_action( 'wpmu_new_blog', 'wpgh_appt_new_blog_created', 10, 6 );

/**
 * Drop our custom tables when a mu site is deleted
 *
 * @since  2.5
 * @param  array $tables  The tables to drop
 * @param  int   $blog_id The Blog ID being deleted
 * @return array          The tables to drop
 */
function wpgh_appt_wpmu_drop_tables( $tables, $blog_id ) {

    switch_to_blog( $blog_id );

    if ( WPGH_APPOINTMENTS()->appointments->installed() ) {
        $tables[] = WPGH_APPOINTMENTS()->appointments->table_name;
        $tables[] = WPGH_APPOINTMENTS()->appointmentmeta->table_name;
        $tables[] = WPGH_APPOINTMENTS()->calendarmeta->table_name;
        $tables[] = WPGH_APPOINTMENTS()->calendar->table_name;
    }

    restore_current_blog();

    return $tables;

}

add_filter( 'wpmu_drop_tables', 'wpgh_appt_wpmu_drop_tables', 10, 2 );

/**
 * Install user roles on sub-sites of a network
 *
 * Roles do not get created when WPGH is network activation so we need to create them during admin_init
 *
 * @since 1.9
 * @return void
 */
function wpgh_appt_install_roles_on_network() {

    WP_Roles();

    global $wp_roles;

    if( ! is_object( $wp_roles ) ) {
        return;
    }

    $admin = get_role( 'administrator' );

    if( ! $admin->has_cap( 'add_calendar' ) ) {

        $roles = new WPGH_Roles_Calendar();
        $roles->add_caps();

    }

}

add_action( 'admin_init', 'wpgh_appt_install_roles_on_network' );

/**
 * Make sure tables were installed correctly.
 */
function wpgh_appt_table_installed_check()
{

    if ( ! WPGH_APPOINTMENTS()->calendar->installed() ){

        WPGH_APPOINTMENTS()->appointments->create_table();
        WPGH_APPOINTMENTS()->appointmentmeta->create_table();
        WPGH_APPOINTMENTS()->calendarmeta->create_table();
        WPGH_APPOINTMENTS()->calendar->create_table();

    }

}

add_action( 'admin_init', 'wpgh_appt_table_installed_check' );