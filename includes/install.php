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

    //install stuff goes here.

    add_filter( 'map_meta_cap',  'meta_caps' , 10, 4 );

    add_roles();
    add_caps();


}

/**
 * Add new shop roles with default WP caps
 *
 * @since 1.4.4
 * @return void
 */
 function add_roles() {
    add_role( 'marketer', __( 'Marketer', 'gruondhogg' ), array(
        'read'                   => true,
        'edit_posts'             => true,
        'delete_posts'           => true,
        'unfiltered_html'        => true,
        'upload_files'           => true,
        'export'                 => true,
        'import'                 => true,
        'delete_others_pages'    => true,
        'delete_others_posts'    => true,
        'delete_pages'           => true,
        'delete_private_pages'   => true,
        'delete_private_posts'   => true,
        'delete_published_pages' => true,
        'delete_published_posts' => true,
        'edit_others_pages'      => true,
        'edit_others_posts'      => true,
        'edit_pages'             => true,
        'edit_private_pages'     => true,
        'edit_private_posts'     => true,
        'edit_published_pages'   => true,
        'edit_published_posts'   => true,
        'manage_categories'      => true,
        'manage_links'           => true,
        'moderate_comments'      => true,
        'publish_pages'          => true,
        'publish_posts'          => true,
        'read_private_pages'     => true,
        'read_private_posts'     => true
    ) );

    add_role( 'sales_manager', __( 'Sales Manager', 'groundhogg' ), array(
        'read'                   => true,
        'edit_posts'             => false,
        'upload_files'           => true,
        'delete_posts'           => false
    ) );
}


/**
 * Tags:
 * - Add Tags
 * - Delete Tags
 * - Edit Tags
 * - Manage Tags (for contacts)
 *
 * Get caps related to managing tags
 *
 * @return array
 */
 function get_calendar_caps()
{
    $caps = array(
        'add_calendar',
        'delete_calendar',
        'edit_calendar',
        'view_calendar',
    );

    return apply_filters( 'wpgh_calendar_caps', $caps );
}
function get_appoinment_caps()
{
    $caps = array(
        'add_appointment',
        'delete_appointment',
        'edit_appointment',
        'view_appointment',
    );

    return apply_filters( 'wpgh_appointment_caps', $caps );
}
/**
 * Add new shop-specific capabilities
 *
 * @since  1.4.4
 * @global WP_Roles $wp_roles
 * @return void
 */
 function add_caps()
{
    global $wp_roles;



    if ( is_object( $wp_roles ) ) {

        $caps =  array_merge( get_appoinment_caps() ,get_calendar_caps());

        /* Add all roles to the Admin levels */
        foreach ( $caps as $cap ){

            $wp_roles->add_cap( 'administrator', $cap );
            $wp_roles->add_cap( 'marketer', $cap );

        }

        /* Sales manager Role */

        /* Contacts*/
        $wp_roles->add_cap( 'sales_manager', 'view_appointment' );
        $wp_roles->add_cap( 'sales_manager', 'view_calendar' );



    }
}




/**
 * Map meta caps to primitive caps
 *
 * @since  2.0
 * @return array $caps
 */
 function meta_caps( $caps, $cap, $user_id, $args ) {

    return $caps;

}


