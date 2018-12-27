<?php
/*
Plugin Name: Groundhogg-Appoinments
Description: Create calendar and Appointments for  contact.
Version: 1.0
Author: Groundhogg Inc.
Author URI: http://www.groundhogg.io
Text Domain: groundhogg
Domain Path: /languages
*/


class GH_APPOINTMENTS
{
    /**
     * @var WPGH_DB_Calendar_Meta
     */
    public $calendarmeta;

    /**
     * @var WPGH_DB_Appointment_Meta
     */
    public $appointmentmeta;

    /**
     * @var WPGH_DB_Appointments
     */
    public $appointments;

    /**
     * @var WPGH_DB_Calendar
     */
    public $calendar;

    public function __construct()
    {
        $this->includes();
        $this->calendarmeta     = new WPGH_DB_Calendar_Meta();
        $this->appointmentmeta  = new WPGH_DB_Appointment_Meta();
        $this->calendar         = new WPGH_DB_Calendar();
        $this->appointments     = new WPGH_DB_Appointments();

        $page  = new WPGH_Calendar_Page();

        if (!$this->calendarmeta->installed()) {
            $this->calendarmeta->create_table();
        }
        if (!$this->appointmentmeta->installed()) {
            $this->appointmentmeta->create_table();
        }
        if (!$this->appointments->installed()) {
            $this->appointments->create_table();
        }
        if (!$this->calendar->installed()) {
            $this->calendar->create_table();
        }
        add_action( 'admin_enqueue_scripts',array($this, 'my_enqueue') );
        add_action( 'wp_enqueue_scripts',array($this, 'my_enqueue') );

        add_action( 'admin_menu', array( $this, 'register' ) );

    }

    public function includes()
    {
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-appointmentmeta.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-calendarmeta.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-appointment.php';
        require_once dirname(__FILE__) . '/includes/class-wpgh-db-calendar.php';
        require_once dirname(__FILE__) . '/includes/admin/class-wpgh-calendar-page.php';

    }



    public function register() {
       add_menu_page('My Page Title', 'DHRUMTI', 'manage_options', 'my-page-slug', array( $this ,'my_function' ) );

    }

    function my_enqueue()
    {
//        wp_enqueue_style( 'calender-css', plugin_dir_url( __FILE__ ) . 'assets/lib/fullcalendar/fullcalendar.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/lib/fullcalendar/fullcalendar.css' ) );
        wp_enqueue_script( 'calender-moment', plugins_url( '/assets/lib/fullcalendar/lib/moment.min.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '/assets/lib/fullcalendar/lib/moment.min.js' ) );
        wp_enqueue_script( 'calender-main', plugins_url( '/assets/lib/fullcalendar/fullcalendar.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '/assets/lib/fullcalendar/fullcalendar.js' ) );
        wp_enqueue_script( 'calender-ui', plugins_url( '/assets/lib/fullcalendar/lib/jquery-ui.min.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . '/assets/lib/fullcalendar/lib/jquery-ui.min.js' ) );

    }
    public function my_function() {
        ?>
        <a href="http://localhost/wp/wp-admin/admin.php?page=gh_calendar">claendar</a>
        <div id='wrap'>

            <div id='external-events'>
                <h4>Drag and Drop Appoinment for Booking</h4>
                <div class='fc-event'>Appointment</div>
            </div>
            <div id='calendar'></div>
            <div style='clear:both'></div>


            <input type="button" name="btndisplay" id ="btnalert" value="Book appointment"/>
        </div>


        <script type="text/javascript">
            jQuery(function($) {
                $('#external-events .fc-event').each(function () {
                    // make the event draggable using jQuery UI
                    $(this).draggable({
                        zIndex: 999,
                        revert: true,      // will cause the event to go back to its
                        revertDuration: 0  //  original position after the drag
                    });
                });

                $('#calendar').fullCalendar({
                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay,listMonth'
                    },
                    businessHours: {
                        // days of week. an array of zero-based day of week integers (0=Sunday)
                        dow: [ 1, 2, 3, 4 ,5], // Monday - Thursday
                        start: '9:00', // a start time (10am in this example)
                        end: '17:00', // an end time (6pm in this example)
                    },
                    editable: true,
                    eventLimit: true, // allow "more" link when too many events
                    selectable :false,

                    minTime : '8:00',
                    maxTime : '18:00',
                    navLinks: true,
                    droppable: true,
                    dayRender: function (date, cell) {
                        if( date < new Date()) {
                            cell.css("background-color", "");
                        }
                    },
                    eventOverlap : false,
                    eventDrop: function(event, delta, revertFunc) {
                        // disable booking previous date
                        var today = new Date();
                        if(event.start < today) {
                            revertFunc();
                            alert('You can not book past Date.');
                        }
                        //get and set values of text box..
                        if( event.id == 'booking_event'  ) {
                            alert( event.start ) ;
                        }
                    },
                    drop: function( date, event, ui, resourceId ){
                        // add event only if event date in grater
                        if( date  > new Date()) {
                            $(this).remove();


                            var newEvent = {
                                title: 'My Appointment',
                                start: date,
                                end : moment(date).add(1,'h'),
                                id : 'booking_event',
                                constraint: 'businessHours',
                                editable: true,
                            };
                            $('#calendar').fullCalendar( 'renderEvent', newEvent , 'stick');
                        } else {
                            alert('You can not book passed date.');
                        }

                    },
                    events: [
                        {
                            title: 'All Day Event',
                            start: '2018-12-01',
                            editable : false,
                        },
                        {
                            title: 'Long Event',
                            start: '2018-12-07',
                            end: '2018-12-10'
                        },
                        {
                            title: 'Click for Google',
                            start: '2018-12-28'
                        },
                        {
                            id : 'booking_event1',
                            title: 'my Event',
                            start: '2018-12-16T13:59:00',
                            end: '2018-12-16T16:59:00',
                            constraint: 'businessHours',
                            editable: true,
                            allDay : false,
                        },
                    ]
                });

                $( "#btnalert" ).click(function() {

                    // get event start and end
                    var event = $("#calendar").fullCalendar( 'clientEvents','booking_event' );
                    if( !( event[0] == null ) ) {
                        console.log(event);
                        alert(event[0].start);
                        alert(event[0].end);

                    } else {
                        alert('Please Drag and Drop appointment.');
                    }
                });

            });
        </script>

        <?php
    }
}


function WPGH_APPOINTMENTS()
{
    return new GH_APPOINTMENTS();
}

if (!class_exists('WPGH_Extension_Manager')) {
    add_action('groundhogg_loaded', 'WPGH_APPOINTMENTS');
} else {
    WPGH_APPOINTMENTS();
}