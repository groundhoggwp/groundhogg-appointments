(function ($, cal, gh) {

    $.fn.serializeFormJSON = function () {

        var o = {};
        var a = this.serializeArray();
        $.each(a, function () {
            if (o[this.name]) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });
        return o;
    };

    var CachedEvent = {};

    window.addEventListener('message', function (event) {
        if (typeof event.data.action !== "undefined" && event.data.action === "getFrameSize") {
            CachedEvent = event;
            postSizing(event)
        }
    });

    function postSizing(event) {

        if (typeof event == "undefined") {
            event = CachedEvent;
        }
        // console.log( CachedEvent );

        var body = document.body, html = document.documentElement;
        var height = Math.max(body.scrollHeight, body.offsetHeight,
            html.clientHeight, html.scrollHeight, html.offsetHeight);
        var width = '100%';
        event.source.postMessage({height: height, width: width, id: event.data.id}, "*");
    }

    $(document).on('click', function () {
        postSizing();
    });

    $.extend(cal, {

        calendar: null,
        spinner: null,
        spinnerOverlay: null,

        init: function () {
            var self = this;
            this.spinner = $('.loader-wrap');
            this.spinnerOverlay = $('.loader-overlay');
            // Outer
            this.timeSlots = $('#time-slots');
            // inner
            this.slots = $('#time-slots-inner');
            this.detailsForm = $('#details-form');
            // this.errors = $('#appointment-errors');

            this.initCalendar();

            this.bookingData = {};

            /* Submit button */
            $('.details-form').on('submit', function (e) {
                var $form = $(this);
                e.preventDefault();
                self.submit($form);
            });

            $(document).on('click', '.appointment-time', function (e) {
                e.preventDefault();
                self.selectAppointment(e.target);
            });
        },

        /* Check that appointment has been seleced */
        submit: function ($form) {

            var self = this;

            this.hideErrors();

            // //check for appointment
            // if (!self.bookingData.start_date || !self.bookingData.end_date) {
            //     self.setErrors(self.invalidDateMsg);
            //     self.showErrors();
            //     return false;
            // }

            var data = $form.serializeFormJSON();
            data._ghnonce = gh._ghnonce;
            data.form_data = $form.serializeArray();
            data.action = 'groundhogg_add_appointment';
            data.booking_data = {
                start_time: self.bookingData.start_date,
                end_time: self.bookingData.end_date,
                calendar_id: self.calendar_id,
                time_zone: jstz.determine().name(),
            };

            /* Check first last & email */
            /* Passed all checks, make the request... */

            self.showSpinner();

            $.ajax({
                type: "post",
                url: self.ajaxurl,
                dataType: 'json',
                data: data,
                success: function (response) {
                    if (response.success) {
                        var $calendarWrap = $('.calendar-form-wrapper');
                        $calendarWrap.hide();
                        $calendarWrap.after('<div class="gh-message-wrapper gh-form-success-wrapper">' + response.data.message + '</div>');
                        if ( response.data.redirect_link ) {
                            window.top.location.replace(response.data.redirect_link);
                        }
                    } else {
                        $form.before(response.data.html);
                    }

                    postSizing();
                    self.hideSpinner();
                    return false;
                }
            });

            return false;
        },

        formatDate: function (date) {
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
                minDate: self.min_date,
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
                    if ($.inArray(self.formatDate(date), self.disabled_days) != -1) {
                        return [false, "", "unavailable"];
                    } else {
                        return [true, "", "available"];
                    }
                }
            });

            this.calendar.on('change', function () {
                self.refreshTimeSlots(self.calendar.val());
            });

        },

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
                        self.setErrors(response.data.html);
                        self.hideTimeSlots();
                        self.hideSpinner();
                    }

                    postSizing();
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

        hideErrors: function () {
            $('.gh-form-errors-wrapper').remove();
        },

        setErrors: function (html) {
            $('.gh-calendar-form').after(html);
        },

        showForm: function () {
            this.detailsForm.removeClass('hidden');
            postSizing();
        },

    });

    $(function () {
        cal.init();
    });

})(jQuery, BookingCalendar, Groundhogg);