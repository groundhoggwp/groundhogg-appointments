<?php

namespace GroundhoggBookingCalendar;

class Roles extends \Groundhogg\Roles
{

    /**
     * Returns an array  of role => [
     *  'role' => '',
     *  'name' => '',
     *  'caps' => []
     * ]
     *
     * In this case caps should just be the meta cap map for other WP related stuff.
     *
     * @return array[]
     */
    public function get_roles()
    {
        return [];
    }

    public function get_administrator_caps()
    {
        return [
            'add_appointment',
            'delete_appointment',
            'edit_appointment',
            'view_appointment',

            'add_calendar',
            'delete_calendar',
            'edit_calendar',
            'view_calendar',
        ];
    }

    public function get_marketer_caps()
    {
        return [
            'add_appointment',
            'delete_appointment',
            'edit_appointment',
            'view_appointment',

            'add_calendar',
            'delete_calendar',
            'edit_calendar',
            'view_calendar',
        ];
    }

    public function get_sales_manager_caps()
    {
        return [
            'view_own_calendar',
            'edit_own_calendar',

            'view_appointment',
            'add_appointment',
            'delete_appointment',
            'edit_appointment',
        ];

    }


    /**
     * Return a cap to check against the admin to ensure caps are also installed.
     *
     * @return mixed
     */
    protected function get_admin_cap_check()
    {
        return 'add_calendar';
    }
}