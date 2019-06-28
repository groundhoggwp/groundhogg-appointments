// (function ($, calendar) {
//     $.extend( calendar, {
//
//         id: null,
//         name: null,
//         calendar: null,
//
//         detailsForm: null,
//         errors: null,
//         bookingData: null,
//         init: function () {
//
//             this.name = $( '#appointment_name' ).val();
//             this.calendar = $( '#appt-calendar' );
//
//             this.slots = $('#select_time');
//             this.detailsForm = $('#details-form');
//             this.errors = $('#appointment-errors');
//             this.bookingData = {};
//
//             /* Submit button */
//             $(document).on('click', '#book_appointment', function (e) {
//                 e.preventDefault();
//                 appt.submit();
//             });
//
//             $(document).on('click', '.appointment-time', function (e) {
//                 e.preventDefault();
//                 appt.selectAppointment(e.target);
//             });
//
//             this.initDatePicker();
//             $('#spinner').hide();
//         },
//
//         initDatePicker: function () {
//
//             var self = this;
//
//             this.calendar.datepicker({
//                 minDate: 0,
//                 // firstDay: 0,
//                 dateFormat: 'yy-mm-dd',
//                 onSelect: function (dateText) {
//
//                     self.refreshTimeSlots(dateText);
//                 }
//             });
//         },
//
//         refreshTimeSlots: function (date) {
//
//             var self = this;
//
//             this.hideTimeSlots();
//             this.hideErrors();
//             this.removeTimeSlots(); //todo
//             var $spinner = $('#spinner');
//             $spinner.show();
//
//             $.ajax({
//                 type: "post",
//                 url: ajaxurl,
//                 dataType: 'json',
//                 data: {
//                     action: 'groundhogg_get_appointments',
//                     date: date,
//                     calendar: self.id,
//                     timeZone: jstz.determine().name(),
//                 },
//                 success: function (response) {
//
//                     // if (response.status === 'failed') {
//                     //     appt.setErrors(response.msg);
//                     //     appt.showErrors();
//                     //     $spinner.hide();
//                     // } else {
//                     //     appt.removeTimeSlots();
//                     //     appt.setTimeSlots(response.slots);
//                     //     appt.showTimeSlots();
//                     //     $spinner.hide();
//                     // }
//
//                     alert('hello') ;
//
//                 }
//             });
//         },
//
//         showErrors: function () {
//             this.hideTimeSlots();
//             $('#appointment-errors').removeClass('hidden');
//         },
//
//
//         setErrors: function (html) {
//             $('#appointment-errors').html(html);
//         },
//
//         setTimeSlots: function (slots) {
//             $.each(slots, function (i, d) {
//                 $('#select_time').append(
//                     '<input type="button" ' +
//                     'class="appointment-time" ' +
//                     'name="appointment_time" ' +
//                     'id="gh_appointment_' + d.start + '" ' +
//                     'data-start_date="' + d.start + '" ' +
//                     'data-end_date="' + d.end + '" value="' + d.name + '"/>'
//                 );
//             });
//         },
//
//         removeTimeSlots: function (slots) {
//             this.slots.html('');
//             //remove data form variable
//             this.bookingData = {
//                 start_date: null,
//                 end_date: null,
//             };
//         },
//
//         showTimeSlots: function () {
//             $('#appointment-errors').addClass('hidden');
//             $('#time-slots').removeClass('hidden');
//         },
//
//         hideTimeSlots: function () {
//             $('#time-slots').addClass('hidden');
//         },
//
//
//         /**
//          *
//          * @param e Node
//          */
//         selectAppointment: function (e) {
//             // get data from hidden field
//
//             var $e = $(e);
//
//             this.bookingData = {
//                 start_date: $e.data('start_date'),
//                 end_date: $e.data('end_date'),
//             };
//
//             /* Remove selected class from all buttons */
//             $('.appointment-time').removeClass('selected');
//             $e.addClass('selected');
//
//             this.showForm();
//         }
//     });
//
//     $(function () {
//         calendar.init();
//     });
// })(jQuery, GroundhoggCalendar);