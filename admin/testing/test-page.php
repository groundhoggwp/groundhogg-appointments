<?php
namespace GroundhoggBookingCalendar\Admin\Testing;

use Groundhogg\Admin\Admin_Page;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;

class Test_Page extends Admin_Page
{

    protected function add_ajax_actions()
    {
        // TODO: Implement add_ajax_actions() method.
    }

    protected function add_additional_actions()
    {
        // TODO: Implement add_additional_actions() method.
    }

    public function get_slug()
    {
        return 'calendar_testing';
    }

    public function get_name()
    {
        return 'TEst Calendar';
    }

    public function get_cap()
    {
        return 'manage_options';
    }

    public function get_item_type()
    {
        // TODO: Implement get_item_type() method.
    }

    public function scripts()
    {
        // TODO: Implement scripts() method.
    }

    public function help()
    {
        // TODO: Implement help() method.
    }

    public function view()
    {
        $calendar = new Calendar( 6 );

        $show = [
            'name' => $calendar->get_name(),
            'periods' => $calendar->get_available_periods(),
            'slots' => $calendar->get_appointment_slots( strtotime( '2019-06-25 00:00:00' ) ),
            'cal_setup' => $calendar->get_as_array(),
        ];

        echo html()->textarea( [ 'value' => wp_json_encode( $show, JSON_PRETTY_PRINT ) ] );
    }
}