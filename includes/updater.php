<?php

namespace GroundhoggBookingCalendar;

use Groundhogg\Email;
use function Groundhogg\get_db;
use function Groundhogg\words_to_key;
use function Groundhogg\get_email_templates;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;

class Updater extends \Groundhogg\Updater
{

    /**
     * A unique name for the updater to avoid conflicts
     *
     * @return string
     */
    protected function get_updater_name()
    {
        return words_to_key( GROUNDHOGG_BOOKING_CALENDAR_NAME );
    }

    /**
     * Get a list of updates which are available.
     *
     * @return string[]
     */
    protected function get_available_updates()
    {
        return [
            '2.0'
        ];
    }

    /**
     * Update to version 2.0
     */
    public function version_2_0()
    {
        //get google client id and secret
        Plugin::$instance->settings->update_option( 'google_calendar_client_id', get_option( 'google_calendar_client_id' ) );
        Plugin::$instance->settings->update_option( 'google_calendar_secret_key', get_option( 'google_calendar_secret_key' ) );

        $calendars = get_db( 'calendars' )->query( [] );

        foreach ( $calendars as $c ) {
            $this->update_calendar( absint( $c->ID ) );
        }

        set_transient( 'groundhogg_upgrade_notice_booking_calendar', 1, WEEK_IN_SECONDS );

        \GroundhoggBookingCalendar\Plugin::$instance->rewrites->add_rewrite_rules();
        flush_rewrite_rules();

    }


    /**
     * Create all the emails and convert availability
     *
     * @param $id
     */
    protected function update_calendar( $id )
    {
        $calendar = new Calendar( $id );

        // max booking period in availability
        $calendar->update_meta( 'max_booking_period_count', absint( 3 ) );
        $calendar->update_meta( 'max_booking_period_type', sanitize_text_field( 'months' ) );

        // Create default emails...
        $templates = get_email_templates();
        // Booked
        $booked = new Email( [
            'title' => $templates[ 'booked' ][ 'title' ],
            'subject' => $templates[ 'booked' ][ 'title' ],
            'content' => $templates[ 'booked' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $calendar->get_user_id(),
        ] );

        $approved = new Email( [
            'title' => $templates[ 'approved' ][ 'title' ],
            'subject' => $templates[ 'approved' ][ 'title' ],
            'content' => $templates[ 'approved' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $calendar->get_user_id(),
        ] );

        $cancelled = new Email( [
            'title' => $templates[ 'cancelled' ][ 'title' ],
            'subject' => $templates[ 'cancelled' ][ 'title' ],
            'content' => $templates[ 'cancelled' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $calendar->get_user_id(),
        ] );

        $rescheduled = new Email( [
            'title' => $templates[ 'rescheduled' ][ 'title' ],
            'subject' => $templates[ 'rescheduled' ][ 'title' ],
            'content' => $templates[ 'rescheduled' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $calendar->get_user_id(),
        ] );

        $reminder = new Email( [
            'title' => $templates[ 'reminder' ][ 'title' ],
            'subject' => $templates[ 'reminder' ][ 'title' ],
            'content' => $templates[ 'reminder' ][ 'content' ],
            'status' => 'ready',
            'from_user' => $calendar->get_user_id(),
        ] );

        $calendar->update_meta( 'emails', [
            'appointment_booked' => $booked->get_id(),
            'appointment_approved' => $approved->get_id(),
            'appointment_rescheduled' => $rescheduled->get_id(),
            'appointment_cancelled' => $cancelled->get_id(),
        ] );

        // set one hour before reminder by default
        $calendar->update_meta( 'reminders', [
            [
                'when' => 'before',
                'period' => 'hours',
                'number' => 1,
                'email_id' => $reminder->get_id()
            ]
        ] );

        //update availability
        $calendar->update_meta( 'rules', [] );
        $dow = $calendar->get_meta( 'dow' );
        $days = [ 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' ];
        $availability = [];
        foreach ( $dow as $d ) {

            $temp_rule = [];
            $temp_rule[ 'day' ] = $days[ $d ];
            $temp_rule[ 'start' ] = $slot1_start = $calendar->get_meta( 'slot1_start_time', true );
            $temp_rule[ 'end' ] = $slot1_end = $calendar->get_meta( 'slot1_end_time', true );
            $availability[] = $temp_rule;

            if ( $calendar->get_meta( 'slot2_status', true ) ) {
                $temp_rule = [];
                $temp_rule[ 'day' ] = $days[ $d ];
                $temp_rule[ 'start' ] = $slot1_start = $calendar->get_meta( 'slot2_start_time', true );
                $temp_rule[ 'end' ] = $slot1_end = $calendar->get_meta( 'slot2_end_time', true );
                $availability[] = $temp_rule;

            }

            if ( $calendar->get_meta( 'slot3_status', true ) ) {
                $temp_rule = [];
                $temp_rule[ 'day' ] = $days[ $d ];
                $temp_rule[ 'start' ] = $slot1_start = $calendar->get_meta( 'slot3_start_time', true );
                $temp_rule[ 'end' ] = $slot1_end = $calendar->get_meta( 'slot3_end_time', true );
                $availability[] = $temp_rule;

            }
        }

        $calendar->update_meta( 'rules', $availability );

    }


}