<?php
/**
 * Created by PhpStorm.
 * User: atty
 * Date: 1/14/2019
 * Time: 2:48 PM
 */

class WPGH_Appointment_Settings_Tab
{

    public function __construct()
    {
        add_action( 'wpgh_settings_post_defaults_init', array( $this, 'setup' ) );
    }

    /**
     * Pass the page and add the required settings
     *
     * @param $S WPGH_Settings_Page
     */
    public function setup( $S )
    {
        $S->add_tab( 'calendar', __( 'Calendar', 'groundhogg' ) );
        $S->add_section( 'google_calendar', __( 'Google API Keys', 'groundhogg' ), 'calendar' );

        /* Client ID */
        $S->add_setting( array(
            'id'        => 'google_calendar_client_id',
            'section'   => 'google_calendar',
            'label'     => __( 'Client ID', 'groundhogg' ),
            'desc'      => __( 'Your Google developer client ID.', 'groundhogg' ),
            'type'      => 'input',
            'atts' => array(
                //keep brackets for backwards compat
                'name'          => 'google_calendar_client_id',
                'id'            => 'google_calendar_client_id',
            )
        ) );

        /* Secret Key */
        $S->add_setting( array(
            'id'        => 'google_calendar_secret_key',
            'section'   => 'google_calendar',
            'label'     => __( 'Secret Key', 'groundhogg' ),
            'desc'      => __( 'Your Google developer Secret Key.', 'groundhogg' ),
            'type'      => 'input',
            'atts' => array(
                //keep brackets for backwards compat
                'name'          => 'google_calendar_secret_key',
                'id'            => 'google_calendar_secret_key',
            )
        ) );
    }
}