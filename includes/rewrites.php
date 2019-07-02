<?php

namespace GroundhoggBookingCalendar;

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
            'index.php?pagenow=calendar&calendar_id=$matches[1]',
            'top'
        );

        // Forms Iframe Template
        add_rewrite_rule(
            '^gh/calendar/([^/]*)/hosted/?$',
            'index.php?pagenow=calendar_hosted&calendar_id=$matches[1]',
            'top'
        );

    }

    /**
     * @param $vars
     * @return array
     */
    public function add_query_vars( $vars )
    {
        $vars[] = 'pagenow';
        $vars[] = 'calendar_id';

        return $vars;
    }

    /**
     * @param $query
     * @return mixed
     */
    public function parse_query( $query )
    {
        $this->map_query_var( $query, 'calendar_id', 'absint' );
        return $query;
    }

    /**
     * @param string $template
     * @return string
     */
    public function template_include( $template = '' )
    {

        $page = get_query_var( 'pagenow' );

        $template_loader = $this->get_template_loader();

        switch ( $page ){
            case 'calendar':
                $template = $template_loader->get_template_part( 'calendar', '', false );
                break;

            case 'calendar_hosted':
                $template = $template_loader->get_template_part( 'hosted', '', false );
                break;
        }

        return $template;
    }

    public function template_redirect( $template = '' )
    {
        // TODO: Implement template_redirect() method.
    }
}