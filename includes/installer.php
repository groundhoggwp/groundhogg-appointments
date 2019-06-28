<?php
namespace GroundhoggBookingCalendar;

use function Groundhogg\words_to_key;

class Installer extends \Groundhogg\Installer
{

    protected function activate()
    {
        // TODO: Implement activate() method.
    }

    protected function deactivate()
    {
        // TODO: Implement deactivate() method.
    }

    /**
     * The path to the main plugin file
     *
     * @return string
     */
    function get_plugin_file()
    {
        return GROUNDHOGG_BOOKING_CALENDAR__FILE__;
    }

    /**
     * Get the plugin version
     *
     * @return string
     */
    function get_plugin_version()
    {
        return GROUNDHOGG_BOOKING_CALENDAR_VERSION;
    }

    /**
     * A unique name for the updater to avoid conflicts
     *
     * @return string
     */
    protected function get_installer_name()
    {
        return words_to_key( GROUNDHOGG_BOOKING_CALENDAR_NAME );
    }
}