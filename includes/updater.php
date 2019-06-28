<?php
namespace GroundhoggBookingCalendar;

use function Groundhogg\words_to_key;

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
            '1.0'
        ];
    }

    /**
     * Update to version 1.0
     */
    public function version_1_0()
    {
        //TODO
    }
}