var ghAppointment = ghAppointment || {};

(function ($,appt) {
    appt = Object.assign( appt, {
        id: null,
        name: null,
        calendar: null,
        timeSlots: null,
        detailsForm:null,
        errors:null,
        bookingData:null,
        init: function()
        {

            this.id             = $( '#calendar_id' ).val();
            this.name           = $( '#appointment_name' ).val();
            this.calendar       = $( '#appt-calendar' );
            this.timeSlots      = $( '#time-slots' );
            this.slots          = $( '#select_time' );
            this.detailsForm    = $( '#details-form' );
            this.errors         = $( '#appointment-errors' );
            this.bookingData    = {};

            /* Submit button */
            $( document ).on( 'click', '#book_appointment', function( e ){
                e.preventDefault();
                appt.submit();
            } );

            $(document).on( 'click', '.appointment-time', function(e){
                e.preventDefault();
                appt.selectAppointment( e.target );
            } );


            this.initDatePicker();
            $('#spinner').hide();
        },

        validateEmail: function (sEmail) {
            var filter = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            if (filter.test(sEmail)) {
                return true;
            } else {
                return false;
            }
        },

    submit: function()
        {
            this.hideErrors();
            //check for appointment


            /* Check that appointment has been seleced */
            if ( ! this.bookingData.start_date || ! this.bookingData.end_date ){
                this.setErrors( this.invalidDateMsg );
                this.showErrors();
                return false;
            }

            /* Check first last & email */
            var $first  =  $('#first_name');
            var $last   =  $('#last_name');
            var $email  =  $('#email');
            var $phone  =  $('#phone');

            var details = {
                'first' : $first.val(),
                'last'  : $last.val(),
                'email' : $email.val(),
                'phone' : $phone.val(),
            };

            /* Check that appointment has been seleced */
            if ( ! details.first || ! details.last || ! details.email || !details.phone ){
                this.setErrors( this.invalidDetailsMsg );
                this.showErrors();
                return false;
            }

            /* validate email */
            if ( ! this.validateEmail( details.email ) ){
                this.setErrors( this.invalidEmailMsg );
                this.showErrors();
                return false;
            }

            /* Passed all checks, make the request... */
            $.ajax({
                type: "post",
                url: appt.ajax_url,
                dataType: 'json',
                data: {
                    action: 'gh_add_appointment_client',
                    start_time: appt.bookingData.start_date,
                    end_time:   appt.bookingData.end_date,
                    email:      details.email,
                    first_name: details.first,
                    last_name:  details.last,
                    phone:      details.phone,
                    calendar_id: appt.id,
                    appointment_name: $('#appointment_name').val(),
                },
                success: function (response) {
                    if (response.status === 'failed') {
                        appt.setErrors(response.msg);
                        appt.showErrors();
                        return false;
                    } else {
                        $( '.gh-calendar-form' ).replaceWith( response.successMsg );
                        $( '.calendar-form-wrapper' ).addClass( 'appointment-success' );
                        return true;
                    }
                }
            });

            return false;
        },

        initDatePicker: function() {

            this.calendar.datepicker({
                minDate:0,
                firstDay: 0,
                dateFormat:'yy-mm-dd',
                onSelect: function( dateText ) {
                    appt.refreshTimeSlots( dateText );
                }
            });
        },

        refreshTimeSlots: function( date )
        {
            this.hideTimeSlots();
            this.hideErrors();
            this.removeTimeSlots(); //todo
            var $spinner    = $('#spinner');
            $spinner.show();

            // console.log( date );
            $spinner.show();
            $.ajax({
                type: "post",
                url: appt.ajax_url,
                dataType: 'json',
                data: {
                    action: 'gh_get_appointment_client',
                    date : date,
                    calendar : appt.id
                },
                success: function (response) {
                    if ( response.status === 'failed' ) {
                        appt.setErrors( response.msg );
                        appt.showErrors();
                        $spinner.hide();
                    } else {
                        appt.removeTimeSlots();
                        appt.setTimeSlots( response.slots );
                        appt.showTimeSlots();
                        $spinner.hide();
                    }
                }
            });
        },

        showErrors: function(){
            this.hideTimeSlots();
            this.errors.removeClass( 'hidden' );
        },

        hideErrors: function(){
            this.errors.addClass( 'hidden' );
        },

        setErrors: function( html )
        {
            appt.errors.html( html );
        },

        setTimeSlots: function( slots )
        {
            $.each( slots, function (i,d) {
                $('#select_time').append(
                    '<input type="button" ' +
                    'class="appointment-time" ' +
                    'name="appointment_time" ' +
                    'id="gh_appointment_'+ d.start +'" ' +
                    'data-start_date="'+ d.start +'" ' +
                    'data-end_date="'+ d.end +'" value="' + d.name + '"/>'
                );
            });
        },

        removeTimeSlots: function( slots ){
            this.slots.html('');
            //remove data form variable
            this.bookingData = {
                start_date: null,
                end_date:  null,
            };
        },

        showTimeSlots: function () {
            this.hideErrors();
            this.timeSlots.removeClass( 'hidden' );
        },

        hideTimeSlots: function () {
            this.timeSlots.addClass( 'hidden' );
        },

        showForm: function()
        {
            this.detailsForm.removeClass( 'hidden' );
        },

        /**
         *
         * @param e Node
         */
        selectAppointment: function ( e ) {
            // get data from hidden field

            var $e = $(e);

            this.bookingData = {
                start_date: $e.data('start_date'),
                end_date:   $e.data('end_date'),
            };

            /* Remove selected class from all buttons */
            $( '.appointment-time' ).removeClass( 'selected' );
            $e.addClass( 'selected' );

            this.showForm();
        }
    } );

    $(function () {
        appt.init();
    })

})(jQuery,ghAppointment);