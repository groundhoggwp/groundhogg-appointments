<?php
/**
 *
 * Imports Google calendar library.
 * Creates client object to access google services.
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class WPGH_Google_Calendar
 */
class WPGH_Google_Calendar
{
    /**
     * Imports require file for google.
     *
     * WPGH_DB_Google_Calendar constructor.
     */
    public function __construct()
    {
        add_action( 'init', array( $this, 'setup_cron_jobs' ) );
        add_action( 'wpgh_sync_calendars', array( $this, 'sync_calendars' ) );
        require_once ( WPGH_APPOINTMENT_PLUGIN_DIR . 'assets/lib/google-api/vendor/autoload.php' );
    }

    /**
     * Create google client object to access google services.
     *
     * @param $calendar_id - id of calendar which client user retrieve
     * @return Google_Client|WP_Error
     */
    public function get_google_client_form_access_token( $calendar_id )
    {
        //get basic client
        $client =  $this->get_basic_client();
        if( is_wp_error( $client ) ) {
           return $client;
        }

        //retrieve access code and validate access code..
        $access_token  =  WPGH_APPOINTMENTS()->calendarmeta->get_meta( $calendar_id,'access_token' , true );
        if( !$access_token ) {
            return  new WP_Error( 'ACCESS_TOKEN', __( "Access token not found!", "groundhogg" ) );
        }

        $client->setAccessToken( $access_token );
        if( $client->isAccessTokenExpired() ) {
            if($client->getRefreshToken()){
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                //save new access token
                WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'access_token',  $client->getAccessToken()  );
            }
        }
        return $client;
    }

    /**
     * set basic details of google clients and return basic client.
     *
     * @return Google_Client|WP_Error
     */
    public function get_basic_client()
    {
        $client_id = get_option('google_calendar_client_id');
        if ( ! $client_id ) {
            return  new WP_Error( 'GOOGLE_CLIENT_ID', __( "Google client id not found.", "groundhogg" ) );
        }

        $client_secret = get_option('google_calendar_secret_key');
        if( !$client_secret ) {
            return  new WP_Error( 'GOOGLE_CLIENT_SECRET', __( "Google client secret not found.", "groundhogg" ) );
        }

        $client = new Google_Client();
        $client->setApplicationName('Groundhogg Google calendar');
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setClientId( $client_id );
        $client->setClientSecret( $client_secret );
        $client->setRedirectUri("urn:ietf:wg:oauth:2.0:oob");
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $guzzleClient = new \GuzzleHttp\Client(array('curl' => array(CURLOPT_SSL_VERIFYPEER => false)));
        $client->setHttpClient($guzzleClient);
        return $client;
    }

    /**
     * Add the event cron job
     */
    public function setup_cron_jobs()
    {
        if ( ! wp_next_scheduled( 'wpgh_sync_calendars' ) ){
            wp_schedule_event( time(), 'twicedaily', 'wpgh_sync_calendars' );
        }
    }

    /**
     * sync all the calendars with google calendars
     */
    public function sync_calendars()
    {
        /* Get Calendars */
        $calendars = WPGH_APPOINTMENTS()->calendar->get_calendars();
        if( $calendars ) {
            foreach ($calendars as $calendar) {
                $this->sync($calendar->ID);
            }
        }
    }

    /**
     * Imports and update appointments from google calendar to Groundhogg - APPOINTMENT calendar.
     *
     * @param $calendar_id
     * @return bool|WP_Error
     */
    public function sync( $calendar_id )
    {
        $access_token  = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id , 'access_token',true) ;
        $google_calendar_id = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id, 'google_calendar_id', true);
        if ( $access_token && $google_calendar_id) {
            $client = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($calendar_id);
            $service = new Google_Service_Calendar($client);
            //check for the calendar
            if ( WPGH_APPOINTMENTS()->google_calendar->is_valid_calendar( $calendar_id ,$google_calendar_id ,$service )) {
                $optParams = array(
                    'orderBy' => 'startTime',
                    'singleEvents' => true,
                    'timeMin' => date('c'),
                );
                $results = $service->events->listEvents($google_calendar_id, $optParams);
                $events = $results->getItems();
                if (! empty($events)) {
                    // update values in data base
                    foreach ($events as $event) {
                        // get event id and check for update
                        if (!($event->start->dateTime === null || $event->end->dateTime === null || empty($event->getAttendees()))) {
                            $appointment_name = sanitize_text_field( stripslashes( $event->getSummary() ) );
                            $note   = sanitize_text_field( stripslashes($event->getDescription() ) );
                            $start  = strtotime( date( $event->start->dateTime ) );
                            $end    = strtotime( date( $event->end->dateTime ) );
                            $find   = strpos($event->getId(), 'ghcalendar');
                            if ($find === false) {
                                //check for attendees if no one found does not sync it
                                $start  = strtotime('+1 minute', $start);
                                $email  = sanitize_email( stripslashes( $event->getAttendees()[0]['email'] ) );
                                $contact = WPGH()->contacts->get_contacts( array( 'email' => $email) );
                                if (count($contact) > 0) {
                                    // create new contact if contact not found
                                    $contact_id = $contact[0]->ID;
                                } else {
                                    $contact_id = WPGH()->contacts->add(array(
                                        'email' => sanitize_email(stripslashes($event->getAttendees()[0]['email'])),
                                    ));
                                }
                                $appointment_id = WPGH_APPOINTMENTS()->appointments->add(array(
                                    'contact_id' => $contact_id,
                                    'calendar_id' => $calendar_id,
                                    'name' => $appointment_name,
                                    'status' => 'pending',
                                    'start_time' => $start,    //strtotime()
                                    'end_time' => $end       //strtotime()
                                ));
                                // Insert meta
                                if ($appointment_id) {
                                    if ($note != '') {
                                        WPGH_APPOINTMENTS()->appointmentmeta->add_meta($appointment_id, 'note', $note);
                                    }
                                    //delete details of event
                                    try {
                                        $service->events->delete($google_calendar_id, $event->getId());
                                    } catch (Exception $e) {
                                        //todo nothing
                                    }
                                    //add event_id with details
                                    $event1 = new Google_Service_Calendar_Event(array(
                                        'id' => 'ghcalendarcid' . $calendar_id . 'aid' . $appointment_id,
                                        'summary' => $appointment_name,
                                        'description' => $note,
                                        'start' => array(
                                            'dateTime' => date('Y-m-d\TH:i:s', $start),
                                            'timeZone' => get_option('timezone_string'),
                                        ),
                                        'end' => array(
                                            'dateTime' => date('Y-m-d\TH:i:s', $end),
                                            'timeZone' => get_option('timezone_string'),
                                        ),
                                        'attendees' => array(
                                            array('email' => $email),
                                        ),
                                    ));
                                    $new_event = $service->events->insert($google_calendar_id, $event1);
                                }

                            } else {
                                $appointment_id = intval(str_replace('ghcalendarcid' . $calendar_id . 'aid', '', $event->getId()));
                                try {
                                    $email = sanitize_email(stripslashes($event->getAttendees()[0]['email']));
                                    $contact = WPGH()->contacts->get_contacts(array('email' => $email));
                                    if (count($contact) > 0) {
                                        // create new contact if contact not found
                                        $contact_id = $contact[0]->ID;
                                    } else {
                                        $contact_id = WPGH()->contacts->add(array(
                                            'email' => sanitize_email(stripslashes($event->getAttendees()[0]['email'])),
                                        ));
                                    }
                                } catch (Exception $e) {
                                    //todo catch error
                                }
                                // update query
                                $status = WPGH_APPOINTMENTS()->appointments->update( $appointment_id, array(
                                    'contact_id' => $contact_id,
                                    'name' => $appointment_name,
                                    'start_time' => $start,
                                    'end_time' => $end
                                ));
                                //update notes
                                if ($status) {
                                    WPGH_APPOINTMENTS()->appointmentmeta->update_meta( $appointment_id, 'note', $note);
                                }
                            }
                        }
                    }
                    return true;
                } else {
                    return new WP_Error( 'NO_EVENTS', __('No events found in Google calendar.', 'groundhogg') );
                }
            } else {
                // calendar not found
                return new WP_Error( 'NO_CALENDAR', __('Google calendar not found.', 'groundhogg') );
            }
        } else {
            return new WP_Error( 'NO_ACCESS_CODE', __( 'Please generate access code to sync appointments. ', 'groundhogg' ) );
        }
    }

    /**
     * Generate access token form authentication code generated by google.
     *
     * @param $calendar_id
     * @param $code -  authentication code generated by google
     * @return Google_Client|WP_Error
     */
    public function generate_access_token( $calendar_id ,  $code )
    {
        $client = $this->get_basic_client();
        if( is_wp_error( $client ) ) {
            return $client;
        }

        $access_token =  $client->fetchAccessTokenWithAuthCode( $code );
        $client->setAccessToken( $access_token );

        // Check to see if there was an error.
        if (array_key_exists('error', $access_token)) {
          return new WP_Error( 'ACCESS_TOKEN_ERROR', __( 'error' , "groundhogg" ) );
        }
        WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'access_token' ,  $client->getAccessToken() );
        // create new calendar
        $google_calendar  =  new Google_Service_Calendar_Calendar();
        // fetch calendar name from DB
        $calendar   = WPGH_APPOINTMENTS()->calendar->get_calendar( $calendar_id );
        $google_calendar->setSummary( $calendar->name );
        $google_calendar->setTimeZone( get_option('timezone_string') );
        $service = new Google_Service_Calendar($client);
        $createdCalendar = $service->calendars->insert( $google_calendar );
        WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'google_calendar_id' , sanitize_text_field( $createdCalendar->getId() ) );
        return $client;

    }

    /**
     * Checks for calendar on linked google account.
     *
     * @param $calendar_id
     * @param $google_calendar_id
     * @param $service
     * @return bool
     */
    public  function is_valid_calendar( $calendar_id , $google_calendar_id ,  $service )
    {
        try {
            $google_calendar = $service->calendars->get( $google_calendar_id );
            return true;
        }  catch (Exception $e) {
            //clear calendar id
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'google_calendar_id' ,'' );
            WPGH_APPOINTMENTS()->calendarmeta->update_meta( $calendar_id , 'access_token' ,'' );
            return false;
        }
    }

    /**
     * AJAX call to generate access code  and sync calendar
     * called from ADMIN_PAGE - from  class-wpgh-calender-page.php file.
     *
     * Requested by AJAX
     */
    public function gh_verify_code()
    {
        $calendar_id = intval( $_POST[ 'calendar' ] ) ;
        if ( isset( $_POST[ 'auth_code' ] ) ) {
            //call method to validate information
            try  {
                $client  = WPGH_APPOINTMENTS()->google_calendar->generate_access_token( $calendar_id ,  trim( $_POST[ 'auth_code' ] ) );
            } catch ( Exception $e ){
                wp_die( json_encode( array(
                    'status' => 'failed',
                    'msg'    => __( 'This code is expired or invalid and make sure you entered correct google clientID and Secret.','groundhogg')
                )));
            }
            if( is_wp_error( $client ) ) {
                wp_die( json_encode( array(
                    'status' => 'failed',
                    'msg'    => __('Something went wrong!','groundhogg')
                )));
            } else {

                // sync all the existing appointment inside calender
                $appointments =  WPGH_APPOINTMENTS()->appointments->get_appointments_by_args( array( 'calendar_id' => $calendar_id ) );
                $service    = new Google_Service_Calendar($client);
                $google_calendar_id = WPGH_APPOINTMENTS()->calendarmeta->get_meta( $calendar_id ,'google_calendar_id' ,true ) ;

                foreach ( $appointments as $appointment )
                {
                    $contact    = WPGH()->contacts->get( $appointment->contact_id );
                    if ( $contact ) {
                        $event      = new Google_Service_Calendar_Event(array(
                            'id' => 'ghcalendarcid'.$appointment->calendar_id.'aid' . $appointment->ID,
                            'summary' => $appointment->name,
                            'description' => WPGH_APPOINTMENTS()->appointmentmeta->get_meta( $appointment->ID , 'note', true ),
                            'start' => array(
                                'dateTime' => date('Y-m-d\TH:i:s', $appointment->start_time),
                                'timeZone' => get_option('timezone_string'),
                            ),
                            'end' => array(
                                'dateTime' => date('Y-m-d\TH:i:s', $appointment->end_time),
                                'timeZone' => get_option('timezone_string'),
                            ),
                            'attendees' => array(
                                array('email' => $contact->email),
                            ),
                        ));
                        $insert = $service->events->insert( $google_calendar_id , $event ) ;
                    }
                }
                wp_die( json_encode( array(
                    'status' => 'success',
                    'msg'    => __( 'your calendar synced successfully!' ,'groundhogg' )
                ) ) );
            }
        }
    }

    /**
     * Delete Appointment from google calendar if it exist.
     * @param $appointment_id
     */
    public function delete_appointment_from_google($appointment_id)
    {
        $appointment_id = intval($appointment_id);
        $appointment    = WPGH_APPOINTMENTS()->appointments->get($appointment_id);
        if ($appointment) {
            $access_token = WPGH_APPOINTMENTS()->calendarmeta->get_meta($appointment->calendar_id, 'access_token', true);
            $google_calendar_id = WPGH_APPOINTMENTS()->calendarmeta->get_meta($appointment->calendar_id, 'google_calendar_id', true);
            if ($access_token && $google_calendar_id) {
                $client = WPGH_APPOINTMENTS()->google_calendar->get_google_client_form_access_token($appointment->calendar_id);
                $service = new Google_Service_Calendar($client);
                try {
                    $service->events->delete($google_calendar_id, 'ghcalendarcid' . $appointment->calendar_id . 'aid' . $appointment->ID);
                } catch (Exception $e) {
                    //nothing todo
                }
            }
        }
    }

}