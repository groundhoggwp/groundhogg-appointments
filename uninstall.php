<?php
/**
 * Uninstall Groundhogg
 *
 * Deletes all the plugin data i.e.
 * 		1. Custom Post types.
 * 		2. Terms & Taxonomies.
 * 		3. Plugin pages.
 * 		4. Plugin options.
 * 		5. Capabilities.
 * 		6. Roles.
 * 		7. Database tables.
 * 		8. Cron events.
 *
 * @package     WPGH
 * @subpackage  Uninstall
 * @copyright   Copyright (c) 2015, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.4.3
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;


include_once dirname( __FILE__ ) . '/groundhogg-appointments.php' ;

if( wpgh_is_option_enabled( 'gh_uninstall_on_delete' ) ) {

    /* delete permissions */
    WPGH_APPOINTMENTS()->role_calendar->remove_caps();

    // Delete the databases
    WPGH_APPOINTMENTS()->calendar->drop();
    WPGH_APPOINTMENTS()->calendarmeta->drop();
    WPGH_APPOINTMENTS()->appointmentmeta->drop();
    WPGH_APPOINTMENTS()->appointments->drop();

}
