<?php

namespace GroundhoggBookingCalendar;


class Template_Loader extends \Groundhogg\Template_Loader
{

    /**
     * Prefix for filter names.
     *
     * @since 1.0.0
     * @type string
     */
    protected $filter_prefix = 'groundhogg_booking_calendar';

    /**
     * Directory name where custom templates for this plugin should be found in the theme.
     *
     * @since 1.0.0
     * @type string
     */
    protected $theme_template_directory = 'groundhogg-templates';

    /**
     * Reference to the root directory path of this plugin.
     *
     * @since 1.0.0
     * @type string
     */
    protected $plugin_directory = GROUNDHOGG_BOOKING_CALENDAR_PATH;
}