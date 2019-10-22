<?php
namespace GroundhoggBookingCalendar\Admin\Calendars;

use function Groundhogg\get_db;
use function Groundhogg\get_request_query;
use function Groundhogg\get_url_var;
use function Groundhogg\html;
use GroundhoggBookingCalendar\Classes\Calendar;
use Groundhogg\Plugin;
use \WP_List_Table;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Calendars_Table extends WP_List_Table {

	/**
	 * TT_Example_List_Table constructor.
	 *
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 */
	public function __construct() {
		// Set parent defaults.
		parent::__construct( array(
			'singular' => 'calendar',     // Singular name of the listed records.
			'plural'   => 'calendars',    // Plural name of the listed records.
			'ajax'     => false,       // Does this table support ajax?
		) );
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
	public function get_columns() {
		$columns = array(
			'calendar_id'     => _x( 'Calendar Name', 'Column label', 'groundhogg-calendar' ),
            'short_code'       => _x( 'Calendar Short Code', 'Column label', 'groundhogg-calendar' ),
			'user_id'         => _x( 'Calendar Owner', 'Column label', 'groundhogg-calendar' ),
			'description'     => _x( 'Description', 'Column label', 'groundhogg-calendar' ),
		);
		return apply_filters( 'wpgh_calendar_columns', $columns );
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * @return array An associative array containing all the columns that should be sortable.
	 */
	protected function get_sortable_columns() {
		$sortable_columns = array(
			'calendar_id'    => array( 'calendar_id', false ),
		);
		return apply_filters( 'wpgh_calendar_sortable_columns', $sortable_columns );
	}

    /**
     * @param object $item convert $item to calendar object
     */
    public function single_row($item)
    {
        echo '<tr>';
        $this->single_row_columns(new Calendar($item->ID));
        echo '</tr>';
    }

    /**
     * Get default row elements...
     *
     * @param $appointment Calendar
     * @param $column_name
     * @param $primary
     * @return string a list of elements
     */
	protected function handle_row_actions($appointment, $column_name, $primary )
    {
        if ( $primary !== $column_name ) {
            return '';
        }
        $actions = array();
        $actions['edit'] = "<span class='edit'><a href='" . admin_url('admin.php?page=gh_calendar&action=edit&tab=settings&calendar=' . $appointment->get_id() ) . "'>" . __('Edit Calendar') . "</a></span>";
        $actions['delete'] = sprintf(
            '<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
            wp_nonce_url(admin_url('admin.php?page=gh_calendar&calendar='. $appointment->get_id() .'&action=delete')),
            /* translators: %s: title */
            esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $appointment->get_id() ) ),
            __( 'Delete Permanently' )
        );
        return $this->row_actions( apply_filters( 'wpgh_calendar_row_actions', $actions, $appointment, $column_name ) );
    }

    /**
     * @param $calendar Calendar
     * @return string
     */
    protected function column_calendar_id( $calendar )
    {
        $name = ( ! $calendar->get_name()  )? '(' . __( 'no name' ) . ')' : $calendar->get_name() ;
        $editUrl = admin_url( 'admin.php?page=gh_calendar&action=edit&calendar=' . $calendar->get_id() );
        $html = "<strong>";
        $html .= "<a class='row-title' href='$editUrl'>{$name}</a>";
        $html .= "</strong>";
        return $html;
    }

    /**
     * Display calendar owner
     *
     * @param $calendar Calendar
     * @return string
     */
    protected function column_user_id( $calendar )
    {
        $user_data     = get_userdata( $calendar->get_user_id() );
        return esc_html( $user_data->user_login . ' (' . $user_data->user_email .')');

    }

    /**
     * populate description column of table
     *
     * @param $calendar Calendar
     * @return string
     */
    protected function column_description( $calendar )
    {
        return esc_html( $calendar->get_description() );
    }

    /**
     * Populate short code column
     *
     * @param $calendar Calendar
     * @return string
     */
    protected function column_short_code( $calendar )
    {
    	return html()->input( array(
		    'type'  => 'text',
		    'name'  => '',
		    'id'    => '',
		    'class' => 'regular-text code',
		    'value' => sprintf( '[gh_calendar id="%d" appointment_name="%s"]', $calendar->get_id(), $calendar->get_name() ) ,
		    'attributes' => ' onfocus="this.select()" readonly',
		    'placeholder' => ''
	    ) );
    }


	/**
	 * For more detailed insight into how columns are handled, take a look at
	 * WP_List_Table::single_row_columns()
	 *
	 * @param object $appointment        A singular item (one full row's worth of data).
	 * @param string $column_name The name/slug of the column to be processed.
	 * @return string Text or HTML to be placed inside the column <td>.
	 */
	protected function column_default($appointment, $column_name ) {

	    do_action( 'wpgh_calendars_custom_columns', $appointment, $column_name );

	    return '';
	}

	/**
	 * Get value for checkbox column.
	 *
	 * @param object $appointment A singular item (one full row's worth of data).
	 * @return string Text to be placed inside the column <td>.
	 */
	protected function column_cb($appointment ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
            $appointment->ID                // The value of the checkbox should be the record's ID.
		);
	}

    /**
     * @return \Groundhogg\DB\DB|\GroundhoggBookingCalendar\DB\Calendar_Meta|\GroundhoggBookingCalendar\DB\Calendars
     */
	protected function get_db()
    {
        return get_db( 'calendars' );
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
	function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = array(); // No hidden columns
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        $data    = [];
        $per_page = absint( get_url_var( 'limit', 30 ) );
        $paged   = $this->get_pagenum();
        $offset  = $per_page * ( $paged - 1 );
        $search  = get_url_var( 's' );
        $order   = get_url_var( 'order', 'DESC' );
        $orderby = get_url_var( 'orderby', $this->get_db()->get_primary_key() );
        $where = [];
        $args = array(
            'where'   => $where,
            'limit'   => $per_page,
            'offset'  => $offset,
            'order'   => $order,
            'search'  => $search,
            'orderby' => $orderby,
        );

        $items = $this->get_db()->query( $args );
        $total = $this->get_db()->count( $args );

//        foreach ($items as $i => $item){
//            $items[$i] = $this->parse_item($item);
//        }

        $this->items = $items;

        // Add condition to be sure we don't divide by zero.
        // If $this->per_page is 0, then set total pages to 1.
        $total_pages = $per_page ? ceil( (int) $total / (int) $per_page ) : 1;

        $this->set_pagination_args( array(
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
        ) );
	}
}