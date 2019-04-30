<?php
/**
 * Upgrade
 *
 * @package     Includes
 * @author      Adrian Tobey <info@groundhogg.io>
 * @copyright   Copyright (c) 2019, Groundhogg Inc.
 * @license     https://opensource.org/licenses/GPL-3.0 GNU Public License v3
 * @since       File available since Release 1.0.16
 */

class WPGH_Upgrade_Appointment{

    /**
     * @var string the version which is registered in the DB
     */
    public $db_version;

    /**
     * @var string the version which is set by the plugin
     */
    public $current_version;

    /**
     * WPGH_Upgrade constructor.
     */
    public function __construct()
    {

        $this->db_version = get_option( 'wpgh_last_upgrade_version_appointments' );

        if ( ! $this->db_version ){
            $this->db_version = '1.1';
            update_option( 'wpgh_last_upgrade_version_appointments', $this->db_version );
        }

        $this->current_version = WPGH_APPOINTMENTS()->version;
        add_action( 'admin_init', array( $this, 'do_upgrades' ) );

    }

    /**
     * Check whether upgrades should happen or not.
     */
    public function do_upgrades()
    {
        /**
         * Check if the current version is larger than the version last checked by the upgrader
         */
        if ( version_compare( $this->current_version, $this->db_version, '>' ) ){
            $this->upgrade_path();
            update_option( 'wpgh_last_upgrade_version_appointments', $this->current_version );
        }

    }

    /**
     * This function is nice and all you have to do is just enter the version you want to update to.
     */
    private function upgrade_path()
    {
        $this->update_to_version( '1.2.1' );
    }

    /**
     * Takes the current version number and converts it to a function which can be clled to perform the upgrade requirements.
     *
     * @param $version string
     * @return bool|string
     */
    private function convert_version_to_function( $version )
    {

        $nums = explode( '.', $version );
        $func = sprintf( 'version_%s', implode( '_', $nums ) );

        if ( method_exists( $this, $func ) ){
            return $func;
        }

        return false;

    }

    private function update_to_version( $version )
    {
        /**
         * Check if the version we want to update to is greater than that of the db_version
         */
        if ( version_compare( $this->db_version, $version, '<' ) ){

            $func = $this->convert_version_to_function( $version );

            if ( $func && method_exists( $this, $func ) ){

                call_user_func( array( $this, $func ) );

                $this->db_version = $version;

                update_option( 'wpgh_last_upgrade_version_appointments', $this->db_version );
            }

        }

    }

    /**
     * Perform the upgrades for version 1.3.1
     * Updates all the time stored as a local time to UTC+0
     */
    public function version_1_2_1()
    {
        $appoinments = WPGH_APPOINTMENTS()->appointments->get_appointments();
        foreach ($appoinments as $appoinment )
        {
            WPGH_APPOINTMENTS()->appointments->update($appoinment->ID , [
                'start_time'=> wpgh_convert_to_utc_0((int)$appoinment->start_time),
                'end_time'=> wpgh_convert_to_utc_0((int)$appoinment->end_time),                   
                
            ]);
        }
    }
}