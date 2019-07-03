// var appointment;
// (function ($) {
//     appointment = {
//         activeDomainBox: null,
//         init: function()
//         {
//             $('#spinner').show();
//             $( '#btnalert' ).on( 'click', function(e){
//                 e.preventDefault();
//                 appointment.addAppointment();
//             } );
//
//             // $( '#verify_code' ).on( 'click', function(e){
//             //     e.preventDefault();
//             //     appointment.verifyCode();
//             // } );
//         },
//         addAppointment: function(){
//             var start_time          = null;
//             var end_time            = null;
//             var id                  = $('#contact_id').val();
//             var note                = $('#appointmentnote').val();
//             var appointment_name    = $('#appointmentname').val();
//             var calendar_id         = $('#calendar_id').val();
//             start_time              = ghAppointment.valueOf().bookingData.start_date;
//             end_time                = ghAppointment.valueOf().bookingData.end_date;
//             if (  start_time === null  || end_time === null ) {
//                 alert( 'Please select appointment.' );
//             } else {
//                 if (id == null) {
//                     // check for contact id
//                     alert('Please select contact.');
//                 } else {
//                     if (appointment_name != '') {
//                         //AJAX Call to add appointment
//                         $.ajax({
//                             type: "post",
//                             url: ajax_object.ajax_url,
//                             dataType: 'json',
//                             data: {
//                                 action: 'gh_add_appointment',
//                                 start_time: start_time,
//                                 end_time: end_time,
//                                 id: id,
//                                 note: note,
//                                 calendar_id : calendar_id,
//                                 appointment_name: appointment_name
//                             },
//                             success: function (response) {
//                                 alert(response.msg);
//                                 //delete appointment event
//                                 //$('#calendar').fullCalendar('removeEvents', 'booking_event');
//                                 // add new ly added event
//                                 $('#calendar').fullCalendar('renderEvent', response.appointment, 'stick');
//                                 // set value to null
//                                 $('#contact_id').val(null).trigger('change');
//                                 $('#appointmentnote').val('');
//                                 $('#appointmentname').val('');
//                                 ghAppointment.valueOf().bookingData.start_date  = null;
//                                 ghAppointment.valueOf().bookingData.end_date    = null;
//                                 // retrieve all available appointments  after booking
//                                 $('#select_time').children().remove();
//                                 //$( '#select_time' ).replaceWith( response.successMsg );
//                                 $('#appt-calendar').val('');
//
//                             }
//                         });
//                     } else {
//                         alert('Please enter appointment name');
//                     }
//                 }
//             }
//
//         },
//         verifyCode : function () {
//
//             var code        = $('#auth_code').val();
//             var calendar    = $('#calendar').val();
//             if ( code != '' ){
//
//                 $('#spinner').show();
//                 $('#auth_code').hide();
//                 $('#verify_code').hide();
//                 $('#generate_access_code').hide();
//                 // ajax request to generate access code
//                 $.ajax({
//                     type: "post",
//                     url: ajax_object.ajax_url,
//                     dataType: 'json',
//                     data: {
//                         action: 'gh_verify_code',
//                         auth_code : code,
//                         calendar : calendar
//                     },
//                     success: function (response) {
//                         alert(response.msg);
//                         $('#auth_code').val('');
//                         $('#spinner').hide();
//                         $('#auth_code').show();
//                         $('#verify_code').show();
//                         $('#generate_access_code').show();
//                     }
//                 });
//
//             } else {
//                 alert('Please enter Validation code.');
//             }
//         }
//     };
//     $(function () {
//         appointment.init();
//     })
// })(jQuery);