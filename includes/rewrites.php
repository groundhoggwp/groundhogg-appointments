<?php

namespace GroundhoggBookingCalendar;

use function Groundhogg\is_managed_page;
use function Groundhogg\managed_rewrite_rule;
use Groundhogg\Utils\Abstract_Rewrites;

class Rewrites extends Abstract_Rewrites
{

    public function get_template_loader()
    {
        return new Template_Loader();
    }

    /**
     * Add the rules
     */
    public function add_rewrite_rules()
    {
        add_rewrite_rule(
            '^gh/calendar/([^/]*)/?$',
            managed_rewrite_rule( 'subpage=calendar&calendar_id=$matches[1]' ),
            'top'
        );

        // Forms Iframe Template
        add_rewrite_rule(
            '^gh/calendar/([^/]*)/hosted/?$',
            'index.php?pagenow=calendar_hosted&calendar_id=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^gh/appointment/([^/]*)/([^/]*)/?$',
            'index.php?pagenow=appointment&appointment_id=$matches[1]&action=$matches[2]',
            'top'
        );

    }

    /**
     * @param $vars
     * @return array
     */
    public function add_query_vars( $vars )
    {
        $vars[] = 'subpage';
        $vars[] = 'action';
        $vars[] = 'calendar_id';
        $vars[] = 'appointment_id';

        return $vars;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function parse_query( $query )
    {
        $this->map_query_var( $query, 'calendar_id', 'absint' );

        // Appointment ID is encrypted for security!
        $this->map_query_var( $query, 'appointment_id', 'urldecode' );
        $this->map_query_var( $query, 'appointment_id', '\Groundhogg\decrypt' );
        $this->map_query_var( $query, 'appointment_id', 'absint' );

        return $query;
    }

    /**
     * @param string $template
     * @return string
     */
    public function template_include( $template = '' )
    {

        if ( ! is_managed_page() ){
            return $template;
        }

        $page = get_query_var( 'subpage' );

        $template_loader = $this->get_template_loader();

        switch ( $page ){
            case 'calendar':
                $template = $template_loader->get_template_part( 'calendar', '', false );
                break;

            case 'calendar_hosted':
                $template = $template_loader->get_template_part( 'hosted', '', false );
                break;

            case 'appointment':
                $template = $template_loader->get_template_part( 'appointment', '', false );
                break;
        }

        return $template;
    }

    public function template_redirect( $template = '' )
    {
        // TODO: Implement template_redirect() method.
    }
}