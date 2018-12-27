<?php

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div id='calendar'></div>

<?php

display_calendar();

function display_calendar ()
{
//get list of appointment
    $calendar_id = $_GET['calendar_id'];
// get all the appointment
    $obj = new WPGH_DB_Appointments();
    $appointments = $obj->get_appointments_by_args(array( 'calendar_id' => $calendar_id ));
    $display_data = array();
    foreach($appointments as $appointment)
    {
        if($appointment->status == 'pending' ) {
            $color = '#dc3545' ;
        } else {
            $color= '#28a745' ;
        }

        $display_data[] = array(
            'id'         => $appointment->ID,
            'title'      => $appointment->name,
            'start'      => $appointment->start_time,
            'end'        => $appointment->end_time,
            'constraint' => 'businessHours',
            'editable'   => false,
            'allDay'     => false,
            'url'        => admin_url( 'admin.php?page=gh_calendar&action=manage&appointment=' . $appointment->ID ),// link to view appointment page
            'color'      => $color
        );
    }
    $json =  json_encode($display_data);
?>
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
                dow: [ 1, 2, 3, 4 , 5 ], // Monday - Thursday
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
            events : <?php echo $json; ?>
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
<?php } ?>
