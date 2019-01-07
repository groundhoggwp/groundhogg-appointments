<?php

if ( ! defined( 'ABSPATH' ) ) exit;

add_appointment();

/*
 *  Display Full calender.
 *  Loads Data inside full calendar.
 *  Handles all the calendar events.
 */
function add_appointment()
{

    //get list of appointment
    $calendar_id = $_GET['calendar'];

    // get all the appointment

    $appointments = WPGH_APPOINTMENTS()->appointments->get_appointments_by_args(array( 'calendar_id' => $calendar_id ));
    $display_data = array();
    foreach($appointments as $appointment)
    {
        if($appointment->status == 'cancelled' ) {
            $color = '#dc3545' ;
        } else if ($appointment->status == 'approved' ){
            $color= '#28a745' ;
        }else{
            $color= '#0073aa' ;
        }

        $display_data[] = array(
            'id'         => $appointment->ID,
            'title'      => $appointment->name,
            'start'      => $appointment->start_time *1000,
            'end'        => $appointment->end_time * 1000,
            'constraint' => 'businessHours',
            'editable'   => true,
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

<div id="col-container" class="wp-clearfix">
    <div id="col-left">
        <div class="col-wrap">
            <div class="form-wrap">
<!--                <div id='external-events'>-->
<!--                    <h4>Drag and Drop Appoinment in calendar and click on Book appointment</h4>-->
<!--                    <div class='fc-event'>Appointment</div>-->
<!--                </div>-->
                <table class="form-table">
                    <tbody><tr class="form-field term-contact-wrap">
                        <th scope="row"><label for="user_id"><?php _e( 'Enter Email' ) ?></label></th>
                        <td>
                            <?php echo WPGH()->html->dropdown_contacts( array(
                                'name'   => 'contact_id',
                                'id'     => 'contact_id',
                            )); ?>
                            <p class="description"><?php _e( 'Enter Client email address.', 'groundhogg' ) ?></p>
                        </td>
                    </tr>
                    <tr class="form-field term-calendar-name-wrap">
                        <th scope="row"><label for="name"><?php _e( 'Name' ) ?></label></th>
                        <td>
                            <input name="name" id="appointmentname"type="text"  size="40" aria-required="true" placeholder="Appointment Name">
                            <p class="description"><?php _e( 'Give nice name for your appointment.', 'groundhogg' ) ?></p>
                        </td>
                    </tr>
                    <tr class="form-field term-calendar-description-wrap">
                        <th scope="row"><label for="description"><?php _e( 'Note' ,'groundhogg'); ?></label></th>
                        <td>
                            <textarea name="description" id="appointmentnote" rows="5" cols="50" class="large-text" placeholder="Enter notes."></textarea>
                            <p class="description"><?php _e( 'Additional note about appointment you want to add.', 'groundhogg' ) ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <input type="button" name="btndisplay" id ="btnalert" value="Book appointment" class="button button-primary"/>
            </div>
        </div>
    </div>
    <div id="col-right">
        <div class="col-wrap">

            <table>

                <tr>
                    <td style="background-color:#0073aa ;width: 50px; height: 10px ;">
                    </td>
                    <td>
                        <b> <?php _e( 'Pending', 'groundhogg' ); ?></b>
                    </td>

                    <td style="background-color:#28a745 ;width: 50px; height: 30px ;">
                    </td>
                    <td>
                        <b> <?php _e( 'Booked', 'groundhogg' ) ;?></b>
                    </td>

                    <td style="background-color:#dc3545 ;width: 50px; height: 30px ;">
                    </td>
                    <td>
                        <b> <?php _e( 'Canceled', 'groundhogg' ) ; ?></b>
                    </td>
                </tr>

            </table>

            <div id='calendar'></div>
            <input type="hidden" id="calendar_id" value="<?php echo $_GET[ 'calendar' ]; ?>">
            <div style='clear:both'></div>
        </div>
    </div>
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
            nowIndicator :true,
            dayRender: function (date, cell) {
                if( date < new Date()) {
                    cell.css("background-color", "");
                }
            },
            eventOverlap : false,
            eventDrop: function(event, delta, revertFunc) {
              // disable booking previous date

                if( (event.start/1000) < <?php echo current_time('timestamp');?>   ) {
                    revertFunc();
                    alert('You can not book past Date.');
                } else {
                    if( event.id != 'booking_event' ){
                        // make a cll if event is not a booking event
                        // make AJAX request to handle reschedule
                        $.ajax({
                            type: "post",
                            url: ajax_object.ajax_url,
                            dataType: 'json',
                            data: {
                                action: 'gh_update_appointment',
                                start_time: moment(event.start).format('YYYY-MM-DD HH:mm:00'),
                                end_time: moment(event.end).format('YYYY-MM-DD HH:mm:00'),
                                id: event.id,
                            },
                            success: function (response) {

                                if (response.status == 'success') {
                                    alert(response.msg);

                                } else {
                                    alert(response.msg);
                                    revertFunc();
                                }
                            }
                        });
                    }
                }
            },

            eventResize: function(event, delta, revertFunc) {
               // handle resizing of event

                if( event.id != 'booking_event' ){
                    // make a cll if event is not a booking event
                    // make AJAX request to handle reschedule
                    $.ajax({
                        type: "post",
                        url: ajax_object.ajax_url,
                        dataType: 'json',
                        data: {
                            action: 'gh_update_appointment',
                            start_time: moment(event.start).format('YYYY-MM-DD HH:mm:00'),
                            end_time: moment(event.end).format('YYYY-MM-DD HH:mm:00'),
                            id: event.id,
                        },
                        success: function (response) {

                            if (response.status == 'success') {
                                alert(response.msg);

                            } else {
                                alert(response.msg);
                                revertFunc();
                            }
                        }
                    });
                }
            },

            dayClick: function(date) {
                var fetchevent = $('#calendar').fullCalendar('clientEvents','booking_event');
                if ( fetchevent[0] == null){
                    if( date /1000 ><?php echo current_time('timestamp');?> ) {
                        var dow =  <?php echo json_encode($dow); ?>;
                        var isvalid = (dow.indexOf(date.day().toString()) > -1);
                        if (isvalid){
                            // check for all day
                            if(date.hours() == 0 && date.minutes() == 0 ){
                                //this is all day event
                                // change view of calendar to date
                                $('#calendar').fullCalendar('changeView', 'agendaDay');
                                $('#calendar').fullCalendar('gotoDate', date);
                                alert('Please slect time slot from this date.');
                            } else {
                                // check for business hours
                                if (!isOverlapping(moment(date).add(1,'minutes') , moment(date).add(1, 'h'))){
                                    // check for overlap
                                    $(this).remove();
                                    var newEvent = {
                                        title: 'My Appointment',
                                        start: moment(date).add(1,'minutes'),
                                        end: moment(date).add(1, 'h'),
                                        id: 'booking_event',
                                        constraint: 'businessHours',
                                        editable: true,
                                    };
                                    $('#calendar').fullCalendar('renderEvent', newEvent, 'stick');
                                } else {
                                    alert('time slot already booked!');
                                }
                            }
                        }else {
                            alert('Out of Business hours !');
                        }
                } else {
                    alert('You can not book passed date.');
                }
                } else {
                    alert('Please move added event !');
                }
            },

            events : <?php echo $json; ?>
        });
        function isOverlapping(start , end ) {
            var arrCalEvents = $('#calendar').fullCalendar('clientEvents');
            for (i in arrCalEvents) {
                    if ((end >= arrCalEvents[i].start && start <= arrCalEvents[i].end) || (end == null && (event.start >= arrCalEvents[i].start && start <= arrCalEvents[i].end))) {//!(Date(arrCalEvents[i].start) >= Date(event.end) || Date(arrCalEvents[i].end) <= Date(event.start))
                        return true;
                    }
            }
            return false;
        }

    });
</script>
<?php } ?>
