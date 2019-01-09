var appointment;
(function ($) {
    appointment = {
        activeDomainBox: null,
        init: function()
        {
            $( '#btnalert' ).on( 'click', function(e){
                e.preventDefault();
                appointment.addAppointment();
            } );
        },
        addAppointment: function(){
            var id          = $('#contact_id').val();
            var start_time  = $('#hidden_data').data('start_date');
            var end_time    = $('#hidden_data').data('end_date') ;
            var note        = $('#appointmentnote').val();
            var appointment_name  = $('#appointmentname').val();
            var calendar_id  = $('#calendar_id').val();

            if ( $('#hidden_data').data('start_date') == ''  ||  $('#hidden_data').data('start_date') == '' ) {
                alert( 'Please select appointment.' );
            } else {
                if (id == null) {
                    // check for contact id
                    alert('Please select contact.');
                } else {

                    if (appointment_name != '') {
                        //AJAX Call to add appointment
                        $.ajax({
                            type: "post",
                            url: ajax_object.ajax_url,
                            dataType: 'json',
                            data: {
                                action: 'gh_add_appointment',
                                start_time: start_time,
                                end_time: end_time,
                                id: id,
                                note: note,
                                calendar_id : calendar_id,
                                appointment_name: appointment_name
                            },
                            success: function (response) {
                                alert(response.msg);
                                //delete appointment event
                                //$('#calendar').fullCalendar('removeEvents', 'booking_event');
                                // add new ly added event
                                $('#calendar').fullCalendar('renderEvent', response.appointment, 'stick');
                                // set value to null
                                $('#contact_id').val(null).trigger('change');
                                $('#appointmentnote').val('');
                                $('#appointmentname').val('');
                                $('#hidden_data').data('start_date' , '');
                                $('#hidden_data').data('end_date'   , '');
                                $('#hidden_data').data('control_id' , '');
                                // retrieve all available appointments  after booking
                                $('#select_time').children().remove();
                                $('#date').val('');

                            }
                        });

                    } else {
                        alert('Please enter appointment name');
                    }
                }
            }

/*
            var event = $("#calendar").fullCalendar( 'clientEvents','booking_event' );
            if( !( event[0] == null ) ) {
                var start_time  = moment(event[0].start).format('YYYY-MM-DD HH:mm:00');
                var end_time    = moment(event[0].end).format('YYYY-MM-DD HH:mm:00') ;
                var id       = $('#contact_id').val();
                var note        = $('#appointmentnote').val();
                var appointment_name  = $('#appointmentname').val();
                var calendar_id  = $('#calendar_id').val();
                if ( id == null ) {
                    // check for contact id
                    alert('Please select contact.');
                } else {
                    if (appointment_name != '') {
                        //AJAX Call to add appointment
                        $.ajax({
                            type: "post",
                            url: ajax_object.ajax_url,
                            dataType: 'json',
                            data: {
                                action: 'gh_add_appointment',
                                start_time: start_time,
                                end_time: end_time,
                                id: id,
                                note: note,
                                calendar_id : calendar_id,
                                appointment_name: appointment_name
                            },
                            success: function (response) {
                                alert(response.msg);
                                //delete appointment event
                                $('#calendar').fullCalendar('removeEvents', 'booking_event');
                                // add new ly added event
                                $('#calendar').fullCalendar('renderEvent', response.appointment, 'stick');
                                // set value to null
                                $('#contact_id').val(null).trigger('change');
                                $('#appointmentnote').val('');
                                $('#appointmentname').val('');
                            }
                        });

                    } else {
                        alert('Please enter appointment name');
                    }
                }

            } else {
                alert('Please create appointment by clicking time slot.');
            }*/
        },
    };
    $(function () {
        appointment.init();
    })
})(jQuery);


