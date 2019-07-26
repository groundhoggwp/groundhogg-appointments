<?php
/**
 * Uninstall Groundhogg Appointments
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
use Groundhogg\Plugin;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;


if( Plugin::$instance->settings->is_option_enabled( 'gh_uninstall_on_delete' ) ) {

    /* delete permissions */
//    \GroundhoggBookingCalendar\Plugin::$instance->roles->remove_roles_and_caps();

    // Delete the databases
//    \Groundhogg\get_db('appointmentmeta')->drop();
//    \Groundhogg\get_db('appointments')->drop();
//    \Groundhogg\get_db('calendarmeta')->drop();
//    \Groundhogg\get_db('calendar')->drop();
}
