<?php
/**
 * Created by PhpStorm.
 * User: Adrian
 * Date: 2018-08-30
 * Time: 10:34 PM
 */

class WPGH_Appointment_Replacements
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
        //get  all the appointments using contact id
        $appointments = (array) WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array('contact_id' => $contact_id) );

        if ($appointments) {
            $appointment  = (array) array_pop( $appointments );
            $local_time = $this->get_contact_appt_timestamp( $contact_id, $appointment[ 'start_time' ] );
            $format  =  get_option('date_format').', '.get_option('time_format');
            return date_i18n( $format, $local_time );
        }

        return '';
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
        $appointments = (array) WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array('contact_id' => $contact_id) );

        if ($appointments) {
            $appointment  = (array) array_pop( $appointments );
            $local_time = $this->get_contact_appt_timestamp( $contact_id, $appointment[ 'end_time' ] );
            $format  =  get_option('date_format'). ', '. get_option('time_format');
            return date_i18n( $format,  $local_time );
        }
        return '';
    }

    protected function get_contact_appt_timestamp( $contact_id, $time )
    {
        $contact = wpgh_get_contact( $contact_id );
        if ( ! $contact->get_time_zone_offset() ){
            return wpgh_convert_to_local_time( absint( $time ) );
        } else {
            return $contact->get_local_time( absint( $time ) );
        }
    }

}