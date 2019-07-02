(function ($, cal) {

    $.extend(cal, {

        calendar: null,
        spinner: null,
        spinnerOverlay: null,

        init: function () {

            var self = this;

            this.spinner = $('.loader-wrap');
            this.spinnerOverlay = $('.loader-overlay');

            this.initCalendar();

            // Outer
            this.timeSlots = $('#time-slots');
            // inner
            this.slots = $('#time-slots-inner');

            this.detailsForm = $('#details-form');
            this.errors = $('#appointment-errors');

            this.bookingData = {};


            /* Submit button */
            $('.details-form').on('submit', function (e) {
                e.preventDefault();
                self.submit();
            });

            $(document).on('submit', '.appointment-time', function (e) {
                e.preventDefault();
                self.selectAppointment(e.target);
            });

            $(document).on('click', '.appointment-time', function (e) {
                e.preventDefault();
                self.selectAppointment(e.target);
            });
        },

        /* Check that appointment has been seleced */
        submit: function () {
            var self = this;
            this.hideErrors();
            var email = $('#email');

            if (!email.val()) {
                alert('Please Enter email.');
                return false;
            }

            //check for appointment
            if (!self.bookingData.start_date || !self.bookingData.end_date) {
                self.setErrors(self.invalidDateMsg);
                self.showErrors();
                return false;

            }

            /* Check first last & email */


            alert (self.bookingData.start_date + ' - ' + self.bookingData.end_date );

            // /* Passed all checks, make the request... */
            // $.ajax({
            //     type: "post",
            //     url: appt.ajax_url,
            //     dataType: 'json',
            //     data: {
            //         action: 'gh_add_appointment_client',
            //         start_time: appt.bookingData.start_date,
            //         end_time: appt.bookingData.end_date,
            //         email: details.email,
            //         first_name: details.first,
            //         last_name: details.last,
            //         phone: details.phone,
            //         calendar_id: appt.id,
            //         appointment_name: $('#appointment_name').val(),
            //     },
            //     success: function (response) {
            //         if (response.status === 'failed') {
            //             appt.setErrors(response.msg);
            //             appt.showErrors();
            //             return false;
            //         } else {
            //             $('.gh-calendar-form').replaceWith(response.successMsg);
            //             $('.calendar-form-wrapper').addClass('appointment-success');
            //
            //             if (response.redirect_link) {
            //                 window.location.replace(response.redirect_link);
            //             }
            //
            //             return true;
            //         }
            //     }
            // });
            //
            // return false;
        },

        formatDate: function(date) {
            var d = date,
                month = '' + (d.getMonth() + 1),
                day = '' + d.getDate(),
                year = d.getFullYear();

            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;

            return [year, month, day].join('-');
        },

        initCalendar: function () {
            var self = this;

            this.calendar = $('#booking-calendar');
            this.calendar.datepicker({
                firstDay: self.start_of_week,
                minDate: 0,
                maxDate: self.max_date,
                changeMonth: false,
                changeYear: false,
                dateFormat: 'yy-mm-dd',
                dayNamesMin: self.day_names,
                // constrainInput: true,
                /**
                 *
                 * @param date Date utc 0
                 * @returns {*[]}
                 */
                beforeShowDay: function (date) {
                    if ( $.inArray( self.formatDate(date), self.disabled_days ) != -1 ) {
                        return [false, "","unavailable"];
                    } else{
                        return [true,"","available"];
                    }
                }
            });

            this.calendar.on('change', function () {
                self.refreshTimeSlots(self.calendar.val());
            });

        },

        /**
         *
         * @param e Node
         */
        selectAppointment: function (e) {
            // get data from hidden field

            var $e = $(e);
            this.bookingData = {
                start_date: $e.data('start_date'),
                end_date: $e.data('end_date'),
            };

            /* Remove selected class from all buttons */
            $('.appointment-time').removeClass('selected');
            $e.addClass('selected');
            this.showForm();
        },

        setTimeSlots: function (slots) {

            var $calWrapper = $('.booking-calendar-column');

            $calWrapper.removeClass('col-1-of-1');
            $calWrapper.addClass('col-2-of-3');

            var self = this;
            $.each(slots, function (i, d) {
                self.slots.append(
                    '<input type="button" ' +
                    'class="appointment-time" ' +
                    'name="appointment_time" ' +
                    'id="gh_appointment_' + d.start + '" ' +
                    'data-start_date="' + d.start + '" ' +
                    'data-end_date="' + d.end + '" value="' + d.display + '"/>'
                );
            });
        },

        refreshTimeSlots: function (date) {

            var self = this;

            this.hideTimeSlots();
            this.hideErrors();
            this.removeTimeSlots(); //todo

            self.showSpinner();

            $.ajax({
                type: "post",
                url: self.ajaxurl,
                dataType: 'json',
                data: {
                    action: 'groundhogg_get_slots',
                    date: date,
                    calendar: self.calendar_id,
                    timeZone: jstz.determine().name(),
                },
                success: function (response) {
                    if (Array.isArray(response.data.slots)) {
                        self.removeTimeSlots();
                        self.setTimeSlots(response.data.slots);
                        self.showTimeSlots();
                        self.hideErrors();
                        self.hideSpinner();
                        self.displayDate(date);


                    } else {
                        self.setErrors(response.data);
                        self.showErrors();
                        self.hideTimeSlots();
                        self.hideSpinner();
                    }
                }
            });
        },


        displayDate: function (date) {
            var d = new Date(date);
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            $('.time-slot-select-text').text(monthNames[d.getMonth()] + ' ' + (d.getDate() + 1) + ' ' + d.getFullYear());

        },

        removeTimeSlots: function (slots) {
            this.slots.html('');
            //remove data form variable
            this.bookingData = {
                start_date: null,
                end_date: null,
            };
        },

        showTimeSlots: function () {
            this.hideErrors();
            this.timeSlots.removeClass('hidden');
        },

        hideTimeSlots: function () {
            this.timeSlots.addClass('hidden');
        },

        showSpinner: function () {
            this.spinner.show();
            this.spinnerOverlay.show();
        },

        hideSpinner: function () {
            this.spinner.hide();
            this.spinnerOverlay.hide();
        },

        showErrors: function () {
            // this.hideTimeSlots();
            this.errors.removeClass('hidden');
        },

        hideErrors: function () {
            this.errors.addClass('hidden');
        },

        setErrors: function (html) {
            this.errors.html(html);
        },

        showForm: function () {
            this.detailsForm.removeClass('hidden');
        },

    });

    $(function () {
        cal.init();
    });

})(jQuery, BookingCalendar);