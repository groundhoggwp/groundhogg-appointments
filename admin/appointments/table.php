<?php

namespace GroundhoggBookingCalendar\Admin\Appointments;

use function Groundhogg\get_contactdata;
use function Groundhogg\get_request_query;
use function Groundhogg\get_request_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Appointment;
use Groundhogg\Plugin;
use \WP_List_Table;

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// WP_List_Table is not loaded automatically so we need to load it in our application
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Appointments_Table extends WP_List_Table
{

    /**
     * TT_Example_List_Table constructor.
     *
     * REQUIRED. Set up a constructor that references the parent constructor. We
     * use the parent reference to set some default configs.
     */
    public function __construct()
    {
        // Set parent defaults.
        parent::__construct(array(
            'singular' => 'appointment',     // Singular name of the listed records.
            'plural' => 'appointments',    // Plural name of the listed records.
            'ajax' => false,       // Does this table support ajax?
        ));
    }

    /**
     * Get a list of columns. The format is:
     * 'internal-name' => 'Title'
     *
     * bulk elements or checkboxes, simply leave the 'cb' entry out of your array.
     *
     * @see WP_List_Table::::single_row_columns()
     * @return array An associative array containing column information.
     */
    public function get_columns()
    {
        $columns = array(
            'appointment_id' => _x('Name', 'Column label', 'groundhogg'),
            'contact' => _x('Contact Name', 'Column label', 'groundhogg'),
            'status' => _x('Status', 'Column label', 'groundhogg'),
            'stat_time' => _x('Start Time', 'Column label', 'groundhogg'),
            'end_time' => _x('End Time', 'Column label', 'groundhogg'),
        );
        return apply_filters('wpgh_appointment_columns', $columns);
    }

    /**
     * Get a list of sortable columns. The format is:
     * 'internal-name' => 'orderby'
     * or
     * 'internal-name' => array( 'orderby', true )
     *
     * @return array An associative array containing all the columns that should be sortable.
     */
    protected function get_sortable_columns()
    {
        $sortable_columns = array(
            'appointment_id' => array('appointment_id', false),
        );
        return apply_filters('wpgh_appointment_sortable_columns', $sortable_columns);
    }


    /**
     * @param object $item convert $item to Appointment object
     */
    public function single_row($item)
    {
        echo '<tr>';
        $this->single_row_columns(new Appointment($item->ID));
        echo '</tr>';
    }

    /**
     * Get default row elements...
     *
     * @param $appointment Appointment
     * @param $column_name
     * @param $primary
     * @return string a list of elements
     */
    protected function handle_row_actions($appointment, $column_name, $primary)
    {
        if ($primary !== $column_name) {
            return '';
        }
        $actions = array();
        $actions['edit'] = "<span class='edit'><a href='" . admin_url('admin.php?page=gh_calendar&action=edit_appointment&appointment=' . $appointment->get_id()) . "'>" . __('Edit') . "</a></span>";
        $actions['delete'] = sprintf(
            '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
            wp_nonce_url( admin_url('admin.php?page=gh_calendar&appointment=' . $appointment->get_id() . '&action=delete_appointment' ) ),
            /* translators: %s: title */
            esc_attr(sprintf(__('Delete &#8220;%s&#8221; permanently'), $appointment->get_id())),
            __('Delete')
        );
        return $this->row_actions(apply_filters('wpgh_calendar_row_actions', $actions, $appointment, $column_name));
    }

    /**
     * @param $appointment Appointment
     * @return string
     */
    protected function column_appointment_id( $appointment )
    {
        $name = (!$appointment->get_name()) ? '(' . __('no name') . ')' : $appointment->get_name();
        $editUrl = admin_url('admin.php?page=gh_calendar&action=edit_appointment&appointment=' . $appointment->get_id());
        $html = "<strong>";
        $html .= "<a class='row-title' href='$editUrl'>{$name}</a>";
        $html .= "</strong>";
        return $html;
    }


    /**
     * @param $appointment Appointment
     * @return string
     */
    protected function column_contact( $appointment )
    {
        $contact = get_contactdata( $appointment->get_contact_id() );
        $name = (!$contact->get_full_name()) ? '(' . __('no name') . ')' : $contact->get_full_name();
        $editUrl = admin_url('admin.php?page=gh_contacts&action=edit&contact=' . $contact->get_id());
        $html = "<a href='$editUrl'>{$name}</a>";
        return $html;

    }

    /**
     * @param $appointment Appointment
     * @return string
     */
    protected function column_status( $appointment )
    {
        return $appointment->get_status() ? $appointment->get_status() : '&#x2014;' ;
    }

    /**
     * @param $appointment Appointment
     * @return false|string
     */
    protected function column_stat_time( $appointment )
    {
        $format = sprintf( "%s %s", get_option( 'date_format' ), get_option( 'time_format' ) );
        return date_i18n( $format,  Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_start_time() ) );
    }

    /**
     * @param $appointment Appointment
     * @return false|string
     */
    protected function column_end_time( $appointment )
    {

        $format = sprintf( "%s %s", get_option( 'date_format' ), get_option( 'time_format' ) );
        return date_i18n( $format,  Plugin::$instance->utils->date_time->convert_to_local_time( $appointment->get_end_time() ) );
    }


    /**
     * For more detailed insight into how columns are handled, take a look at
     * WP_List_Table::single_row_columns()
     *
     * @param object $appointment A singular item (one full row's worth of data).
     * @param string $column_name The name/slug of the column to be processed.
     * @return string Text or HTML to be placed inside the column <td>.
     */
    protected function column_default($appointment, $column_name)
    {

        do_action('wpgh_appointments_custom_columns', $appointment, $column_name);
        return '';
    }

    /**
     * Get value for checkbox column.
     *
     * @param object $appointment A singular item (one full row's worth of data).
     * @return string Text to be placed inside the column <td>.
     */
    protected function column_cb($appointment)
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
            $appointment->ID                // The value of the checkbox should be the record's ID.
        );
    }


    /**
     * Prepares the list of items for displaying.
     *
     * REQUIRED! This is where you prepare your data for display. This method will
     *
     * @global wpdb $wpdb
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
     */
    function prepare_items()
    {
        /*
         * First, lets decide how many records per page to show
         */
        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

        $query = get_request_query();
        $query['calendar_id'] = get_request_var('calendar' );

        $data = Plugin::$instance->dbs->get_db('appointments')->query($query);

        usort($data, array($this, 'usort_reorder'));

        $current_page = $this->get_pagenum();

        $total_items = count($data);

        $data = array_slice($data, (($current_page - 1) * $per_page), $per_page);

        $this->items = $data;
        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args(array(
            'total_items' => $total_items,                     // WE have to calculate the total number of items.
            'per_page' => $per_page,                        // WE have to determine how many items to show on a page.
            'total_pages' => ceil($total_items / $per_page), // WE have to calculate the total number of pages.
        ));
    }

    /**
     * Callback to allow sorting of example data.
     *
     * @param string $a First value.
     * @param string $b Second value.
     *
     * @return int
     */
    protected function usort_reorder($a, $b)
    {
        $a = (array)$a;
        $b = (array)$b;

        // If no sort, default to title.
        $orderby = !empty($_REQUEST['orderby']) ? wp_unslash($_REQUEST['orderby']) : 'date_scheduled'; // WPCS: Input var ok.
        // If no order, default to asc.
        $order = !empty($_REQUEST['order']) ? wp_unslash($_REQUEST['order']) : 'asc'; // WPCS: Input var ok.
        // Determine sort order.
        $result = strnatcmp($a[$orderby], $b[$orderby]);
        return ('desc' === $order) ? $result : -$result;
    }
}