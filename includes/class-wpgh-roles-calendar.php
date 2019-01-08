<?php
/**
 * Roles and Capabilities
 *
 * @package     Includes
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2018, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @since       File available since Release 0.9
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WPGH_Roles Class
 *
 * This class handles the role creation and assignment of capabilities for those roles.
 *
 * These roles let us have Sales People, Marketers, etc, each of whom can do
 * certain things within the CRM
 *
 * @since 1.4.4
 */
class WPGH_Roles_Calendar {

    /**
     * Get things going
     *
     */
    public function __construct() {
        add_filter( 'map_meta_cap', array( $this, 'meta_caps' ), 10, 4 );
    }


    /**
     * array for calendar roles
     *
     * @return mixed|void
     */
    public function get_calendar_caps()
    {
        $caps = array(
            'add_calendar',
            'delete_calendar',
            'edit_calendar',
            'view_calendar',
        );

        return apply_filters( 'wpgh_calendar_caps', $caps );
    }

    /**
     * array for appointment role
     *
     * @return mixed|void
     */
    public function get_appoinment_caps()
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
     * Returns a list of all the caps added by GH
     *
     * @return array
     */
    public function get_gh_caps()
    {
        $caps = array_merge(
            $this->get_calendar_caps(),
            $this->get_appoinment_caps()
        );

        return $caps;
    }

    /**
     * Add new shop-specific capabilities
     */
	public function add_caps()
    {
		global $wp_roles;
		if ( class_exists('WP_Roles') ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}
		if ( is_object( $wp_roles ) ) {
            $caps = $this->get_gh_caps();
            /* Add all roles to the Admin levels */
            foreach ( $caps as $cap ) {
                $wp_roles->add_cap( 'administrator', $cap );
                $wp_roles->add_cap( 'marketer', $cap );
            }
            /* Sales manager Role */
            /* Contacts*/
            $wp_roles->add_cap( 'sales_manager', 'view_calendar' );
            $wp_roles->add_cap( 'sales_manager', 'view_appointment' );
		}
	}

    /**
     * Map meta caps to primitive caps
     *
     * @param $caps
     * @param $cap
     * @param $user_id
     * @param $args
     * @return mixed
     */
	public function meta_caps( $caps, $cap, $user_id, $args ) {
		return $caps;
	}

    /**
     * Remove core post type capabilities (called on uninstall)
     */
	public function remove_caps() {

		global $wp_roles;
		if ( class_exists( 'WP_Roles' ) ) {
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
		}
		if ( is_object( $wp_roles ) ) {
			/* Shop Manager Capabilities */
            $caps = $this->get_gh_caps();
            /* Add all roles to the Admin levels */
            foreach ( $caps as $cap ) {
                $wp_roles->remove_cap( 'administrator', $cap );
                $wp_roles->remove_cap( 'marketer', $cap );
            }
            /* Sales manager Role */
            /* Contacts*/
            $wp_roles->remove_cap( 'sales_manager', 'view_calendar' );
            $wp_roles->remove_cap( 'sales_manager', 'view_appointment' );
        }
	}

    /**
     * Get the appropriate message for when a user doesn't have a cap.
     * @param $cap
     * @return string
     */
	public function error( $cap ){

	    $error_str = str_replace( '_', ' ',  $cap  );
	    $error = sprintf( __( 'Your user role does not have the required permissions to %s.', 'groundhogg' ), $error_str );
        return $error;
    }

}
