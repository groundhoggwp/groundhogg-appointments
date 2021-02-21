<?php

namespace GroundhoggBookingCalendar;

use Exception;
use Google_Service_Calendar;
use Google_Service_Calendar_Calendar;
use Groundhogg\Contact;
use function Groundhogg\get_array_var;
use function Groundhogg\get_contactdata;
use function Groundhogg\get_db;
use function Groundhogg\get_request_var;
use Groundhogg\Plugin;
use GroundhoggBookingCalendar\Classes\Calendar;
use GroundhoggBookingCalendar\Classes\Appointment;
use \Google_Client;
use \WP_Error;

/**
 *
 * Imports Google calendar library.
 * Creates client object to access google services.
 *
 */
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Class Google_Calendar
 */
class Google_Calendar
{
    /**
     * Imports require file for google.
     *
     * WPGH_DB_Google_Calendar constructor.
     */
    public function __construct()
    {
        add_action( 'init', array( $this, 'setup_cron_jobs' ) );
        add_action( 'groundhogg_sync_calendars', array( $this, 'sync_calendars' ) );

//        require_once GROUNDHOGG_BOOKING_CALENDAR_ASSETS_PATH . '/lib/google-api-new/vendor/autoload.php'; // version 2.7.0
        require_once GROUNDHOGG_BOOKING_CALENDAR_PATH . '/vendor/autoload.php'; // version 2.7.0
    }

    /**
     * Create google client object to access google services.
     *
     * @param $calendar_id - id of calendar which client user retrieve
     * @return Google_Client|WP_Error
     */
    public function get_google_client_from_access_token( $calendar_id )
    {
        //get basic client
        $client = $this->get_basic_client();
        if ( is_wp_error( $client ) ) {
            return $client;
        }


        $calendar = new Calendar( $calendar_id );

        //retrieve access code and validate access code..
        $access_token = $calendar->get_access_token();

        if ( !$access_token ) {
            return new WP_Error( 'ACCESS_TOKEN', __( "Access token not found!", 'groundhogg-calendar' ) );
        }

        $client->setAccessToken(  $access_token ) ;

        if ( $client->isAccessTokenExpired() ) {
            if ( $client->getRefreshToken() ) {

                $response = Plugin::instance()->proxy_service->request( 'authentication/refresh', [
                    'token' => $calendar->get_meta('access_token',true),
                    'slug' => 'google'
                ] );

                if ( is_wp_error( $response ) ) {
                    return new WP_Error( 'rest_error' ,  $response->get_error_message()  );
                }

                $access_token = json_encode( get_array_var( $response, 'token' ) );

                if ( !$access_token ) {
                    return new WP_Error(  'no_token', __( 'Could not retrieve access token.', 'groundhogg-calendar' ) );
                }

                $client->setAccessToken( $access_token ) ;

                $calendar->update_meta( 'access_token',  $access_token  );

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
        $client = new Google_Client();
        $client->setApplicationName( 'Groundhogg Google calendar' );
        $client->setScopes( Google_Service_Calendar::CALENDAR );
        $client->setRedirectUri( "urn:ietf:wg:oauth:2.0:oob" );
        $client->setAccessType( 'offline' );
        $client->setPrompt( 'select_account consent' );
        $guzzleClient = new \GuzzleHttp\Client( array( 'curl' => array( CURLOPT_SSL_VERIFYPEER => false ) ) );
        $client->setHttpClient( $guzzleClient );
        return $client;
    }

    /**
     * Add the event cron job
     */
    public function setup_cron_jobs()
    {
        if ( !wp_next_scheduled( 'groundhogg_sync_calendars' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'groundhogg_sync_calendars' );
        }
    }

    /**
     * sync all the calendars with google calendars
     */
    public function sync_calendars()
    {
        /* Get Calendars */
        $calendars = get_db( 'calendars' )->query();
        if ( $calendars ) {
            foreach ( $calendars as $calendar ) {
                $this->sync( $calendar->ID );
            }
        }
    }

    /**
     * Imports and update appointments from google calendar to Groundhogg - APPOINTMENT calendar.
     *
     * @param $id int the calendar ID
     * @return bool|WP_Error
     */
    public function sync( $id )
    {
        $calendar = new Calendar( $id );

        $access_token = $calendar->get_access_token();
        $google_calendar_id = $calendar->get_google_calendar_id();

        if ( !$access_token || !$google_calendar_id ) {
            return new WP_Error( 'no_access_code', __( 'Please generate access code to sync appointments. ', 'groundhogg-calendar' ) );
        }

        $client = $this->get_google_client_from_access_token( $calendar->get_id() );

        if( is_wp_error($client) ) {
            return new WP_Error( $client->get_error_code(), $client->get_error_message() );
        }

        $service = new Google_Service_Calendar( $client );

        if ( !$this->is_valid_calendar( $calendar->get_id(), $google_calendar_id, $service ) ) {
            return new WP_Error( 'no_calendar', __( 'Google calendar not found.', 'groundhogg-calendar' ) );
        }

        //check for the calendar
        $optParams = array(
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => date( DATE_RFC3339 ),
	        'timeZone' => 'UTC'
        );

        $results = $service->events->listEvents( $google_calendar_id, $optParams );
        $events = $results->getItems();

        if ( empty( $events ) ) {
            return new WP_Error( 'no_events', __( 'No future appointments found.', 'groundhogg' ) );
        }

        // update values in data base
        foreach ( $events as $event ) {
            // get event id and check for update
            if ( !( $event->start->dateTime === null || $event->end->dateTime === null || empty( $event->getAttendees() ) ) ) {

                $start = strtotime( $event->start->dateTime  );
                $end = strtotime( $event->end->dateTime );

                $find = strpos( $event->getId(), 'ghcalendar' );
                $email = sanitize_email( stripslashes( $event->getAttendees()[ 0 ][ 'email' ] ) );

                /**
                 * @var $contact Contact
                 */
                $contact = get_contactdata( $email );

                if ( !$contact ) {
                    $contact = new Contact( [
                        'email' => $email
                    ] );
                }

                if ( $find === false ) {
                    //check for attendees if no one found does not sync it
                    $appointment = $calendar->schedule_appointment( [
                        'contact_id' => $contact->get_id(),
                        'calendar_id' => $calendar->get_id(),
                        'name' => sanitize_text_field( stripslashes( $event->getSummary() ) ),
                        'status' => 'pending',
                        'start_time' => $start,
                        'end_time' => $end,
                        'notes' => sanitize_text_field( stripslashes( $event->getDescription() ) )
                    ] );

                    if ( $appointment->exists() ) {
                        // delete google event from google and sync it.
                        try {
                            $service->events->delete( $google_calendar_id, $event->getId() );
                        } catch ( Exception $e ) {
                            //todo nothing
                        }
                    }

                } else {

                    //get appointment id form the google event id
                    $appointment = new Appointment( absint( str_replace( 'ghcalendarcid' . $calendar->get_id() . 'aid', '', $event->getId() ) ) );

                    // check for update in data
                    $is_update = false;

                    if ( !( $appointment->get_name() === sanitize_text_field( stripslashes( $event->getSummary() ) ) ) ) {
                        $is_update = true;
                    }

                    if ( (int) $appointment->get_start_time() !== $start ) {
                        $is_update = true;
                    }

                    if ( (int) $appointment->get_end_time() !== $end ) {
                        $is_update = true;
                    }

                    if ( $appointment->get_meta( 'notes' ) !== sanitize_text_field( stripslashes( $event->getDescription() ) ) ) {
                        $appointment->update_meta( 'notes', sanitize_text_field( stripslashes( $event->getDescription() ) ) );
                    }

                    if ( $is_update ) {
                        $status = $appointment->reschedule( [
                            'contact_id' => $contact->get_id(),
                            'name' => sanitize_text_field( stripslashes( $event->getSummary() ) ),
                            'start_time' => $start,
                            'end_time' => $end,
                        ] );
                    }
                }
            }
        }

        return true;
    }

    /**
     * Checks for calendar on linked google account.
     *
     * @param $calendar_id
     * @param $google_calendar_id
     * @param $service
     * @return bool
     */
    public function is_valid_calendar( $calendar_id, $google_calendar_id, $service )
    {
        $calendar = new Calendar( $calendar_id );
        try {
            $google_calendar = $service->calendars->get( $google_calendar_id );
            return true;
        } catch ( Exception $e ) {
            //clear calendar id
            $calendar->update_meta( 'google_calendar_id', '' );
            $calendar->update_meta( 'access_token', '' );
            return false;
        }
    }
}