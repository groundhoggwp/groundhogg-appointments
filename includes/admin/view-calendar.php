<?php

if ( ! defined( 'ABSPATH' ) ) exit;
?>


<div id='wrap'>
    <div id='external-events'>
        <h4>Drag and Drop Appoinment in calendar and click on Book appointment</h4>
        <div class='fc-event'>Appointment</div>
    </div>
    <input type="button" name="btndisplay" id ="btnalert" value="Book appointment"/>
    <div id='calendar'></div>
    <div style='clear:both'></div>

</div>

<div id='calendar'></div>

<?php

display_calendar();

function display_calendar ()
{
//get list of appointment
    $calendar_id = $_GET['calendar'];
// get all the appointment

    $appointments = WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array( 'calendar_id' => $calendar_id ));
    $display_data = array();
    foreach($appointments as $appointment)
    {
        if($appointment->status == 'cancel' ) {
            $color = '#ffc107' ;
        } else if ($appointment->status == 'booked' ){
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
            'url'        => admin_url( 'admin.php?page=gh_calendar&action=view_appointment&appointment=' . $appointment->ID ),// link to view appointment page
            'color'      => $color
        );
    }
    $json =  json_encode($display_data);

    $dow        = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'dow',true);
    $start_time = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'start_time', true);
    $end_time   = WPGH_APPOINTMENTS()->calendarmeta->get_meta($calendar_id,'end_time', true);

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
                dow: <?php echo json_encode($dow); ?>, // Monday - Thursday
                start: '<?php echo $start_time;  ?>', // a start time (10am in this example)
                end: '<?php echo $end_time; ?>', // an end time (6pm in this example)
            },
            editable: true,
            eventLimit: true, // allow "more" link when too many events
            selectable :false,
            minTime : '<?php echo $start_time;  ?>',
            maxTime : '<?php echo $end_time; ?>',
            navLinks: true,
            droppable: true,
            allDaySlot :false,
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
                    // check for all day
                    if(date.hours() == 0 && date.minutes() == 0 ){
                        //this is all day event
                        // change view of calendar to date
                        $('#calendar').fullCalendar('changeView', 'agendaDay');
                        $('#calendar').fullCalendar('gotoDate', date);
                    } else {
                        console.log(date.hours());
                        console.log(date.minutes());

                        $(this).remove();
                        var newEvent = {
                            title: 'My Appointment',
                            start: date,
                            end: moment(date).add(1, 'h'),
                            id: 'booking_event',
                            constraint: 'businessHours',
                            editable: true,
                        };
                        $('#calendar').fullCalendar('renderEvent', newEvent, 'stick');
                    }
                } else {
                    alert('You can not book passed date.');
                }
            },
            events : <?php echo $json; ?>
        });
        $( "#btnalert" ).click(function() {

            // ajax call on button click

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
