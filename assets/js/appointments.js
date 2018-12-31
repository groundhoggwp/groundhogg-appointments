
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
            var event = $("#calendar").fullCalendar( 'clientEvents','booking_event' );
            if( !( event[0] == null ) ) {

                var start_time  = moment(event[0].start).format('YYYY-MM-DD HH:mm:00');
                var end_time    = moment(event[0].end).format('YYYY-MM-DD HH:mm:00') ;
                var email       = $('#appointmentemail').val();
                var note        = $('#appointmentnote').val();
                var appointment_name  = $('#appointmentname').val();
                var calendar_id  = $('#calendar_id').val();

                if (appointment_name != '') {
                    // validate email address
                    if (!validateEmail(email)) {

                        alert('Please Enter valid email address');

                    } else {
                        //AJAX Call to add appointment
                        $.ajax({
                            type: "post",
                            url: ajax_object.ajax_url,
                            dataType: 'json',
                            data: {
                                action: 'gh_add_appointment',
                                start_time: start_time,
                                end_time: end_time,
                                email: email,
                                note: note,
                                calendar_id : calendar_id,
                                appointment_name: appointment_name
                            },
                            success: function (response) {
                                alert(response.msg);

                                $('#calendar').fullCalendar('removeEvent', 'booking_event');

                                $('#calendar').fullCalendar('renderEvent', response.appointment, 'stick');


                            }
                        });
                    }

                    function validateEmail($email) {
                        var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
                        return emailReg.test($email);
                    }
                } else {
                    alert('Please enter appointment name');
                }
            } else {
                alert('Please Drag and Drop appointment.');
            }
        },
    };

    $(function () {
        appointment.init();
    })

})(jQuery);


