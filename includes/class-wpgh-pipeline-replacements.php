<?php
/**
 * Created by PhpStorm.
 * User: Adrian
 * Date: 2018-08-30
 * Time: 10:34 PM
 */

class WPGH_Pipeline_Replacements
{
    /**
     * @var WPGH_Replacements
     */
    public $replacements;

    public function __construct()
    {
        $this->replacements = WPGH()->replacements;

        $this->setup_codes();
    }

    private function setup_codes()
    {

        $replacements = array(

            array(
                'code'        => 'appointment_start_date_time',
                'callback'    => array( $this, 'appointment_start_date_time' ),
                'description' => __( 'Returns the start date & time of a contact\'s most recently booked appointment.', 'groundhogg' ),
            ),
            array(
                'code'        => 'appointment_end_date_time',
                'callback'    => array( $this, 'appointment_end_date_time' ),
                'description' => __( 'Returns the end date & time of a contact\'s most recently booked appointment.', 'groundhogg' ),
            ),
        );

        $replacements = apply_filters( 'wpgh_pipeline_replacement_defaults', $replacements );

        foreach ( $replacements as $replacement )
        {
            $this->replacements->add( $replacement['code'], $replacement[ 'callback' ], $replacement[ 'description' ] );
        }

    }

    /**
     * return the start date & time
     *
     * @param $contact_id int
     *
     * @return string
     */
    public function appointment_start_date_time( $contact_id )
    {
        wp_die();

        //get  all the appointments using contact id
        $appoinmnents = (array) WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array('contact_id' => $contact_id) );
        $appointment  = (array) $appoinmnents[0];
        return date_i18n( 'D, F j, Y, g:i a', $appointment[ 'start_time' ] );
    }

    /**
     * return the start date & time
     *
     * @param $contact_id int
     *
     * @return string
     */
    public function appointment_end_date_time( $contact_id )
    {
        //get  all the appointments using contact id
        $appoinmnents = (array) WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array('contact_id' => $contact_id) );
        $appointment  = (array) $appoinmnents[0];
        return date_i18n( 'D, F j, Y, g:i a', $appointment[ 'end_time' ] );
    }

}