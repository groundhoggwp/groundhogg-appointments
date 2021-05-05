(function ($, calendar) {
  $.extend(calendar, {
    date: null,
    bookingData: null,
    init: function () {

      var self = this

      this.datePicker = $('#date-picker')
      this.bookingData = {}

      self.initCalendar()

      $(document).on('click', '.appointment-time', function (e) {
        e.preventDefault()
        self.selectAppointment(e.target)
      })

      $('#btnalert').on('click', function (e) {
        e.preventDefault()
        self.addAppointment()
      })

      var calendarEl = document.getElementById('calendar')

      self.fullCalendar = new FullCalendar.Calendar(calendarEl, {
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,dayGridWeek,listWeek'
        },
        businessHours: calendar.business_hours,
        initialView: 'timeGridWeek',
        events: calendar.events
      })

      self.fullCalendar.render()
    },

    addAppointment: function () {

      var self = this

      if (!$('#contact-id').val()) {
        alert('Please select contact.')
        return
      }

      if (!calendar.valueOf().bookingData.start_date) {
        alert('Please select an appointment.')
        return
      }

      if (!calendar.valueOf().bookingData.end_date) {
        alert('Please select an appointment.')
        return
      }

      $('.spinner').css('visibility', 'visible')
      $('#btnalert').prop('disabled', true)

      adminAjaxRequest(
        {
          action: 'groundhogg_add_appointments',
          start_time: calendar.valueOf().bookingData.start_date,
          end_time: calendar.valueOf().bookingData.end_date,
          contact_id: $('#contact-id').val(),
          additional: $('#additional').val(),
          calendar_id: self.item.ID,
        },
        function callback (response) {
          // Handler

          $('.spinner').hide()
          $('#btnalert').prop('disabled', false)

          if (response.success) {
            self.fullCalendar.addEvent(response.data.appointment)
            alert(response.data.msg)
            calendar.clearData()

          } else {
            alert(response.data)
          }
        },
        function (err) {
          console.log(err)
          $('.spinner').css('visibility', 'hidden')
          $('#btnalert').prop('disabled', false)
        }
      )
    },

    initCalendar: function () {

      var self = this

      this.datePicker.datepicker({
        firstDay: self.start_of_week,
        minDate: self.min_date,
        maxDate: self.max_date,
        changeMonth: false,
        changeYear: false,
        dateFormat: 'yy-mm-dd',
        dayNamesMin: self.day_names,
        onSelect: function (time, picker) {
          self.refreshTimeSlots(time, picker)
        },
        beforeShowDay: function (date) {
          // console.debug(date)
          if ($.inArray(self.formatDate(date), self.disabled_days) !== -1) {
            return [false, '', 'unavailable']
          } else {
            return [true, '', 'available']
          }
        }
      })

    },

    formatDate: function (date) {
      var d = date,
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear()

      if (month.length < 2) month = '0' + month
      if (day.length < 2) day = '0' + day

      return [year, month, day].join('-')
    },

    clearData: function () {
      $('#booking-form').trigger('reset')
      calendar.bookingData.start_date = null
      calendar.bookingData.end_date = null
    },

    refreshTimeSlots: function (date, picker) {

      var self = this

      self.hideTimeSlots()
      self.removeTimeSlots()

      console.log(date)

      adminAjaxRequest(
        { action: 'groundhogg_get_appointments', date: date, calendar: self.item.ID },
        function callback (response) {
          // Handler
          if (Array.isArray(response.data.slots)) {

            self.setTimeSlots(response.data.slots)
            self.showTimeSlots()
            self.hideErrors()
          } else {

            self.setErrors(response.data)
            self.hideTimeSlots()
            self.showErrors()
          }
        },
      )
    },

    showErrors: function () {
      this.hideTimeSlots()
      $('#appointment-errors').removeClass('hidden')
    },

    hideErrors: function () {
      $('#appointment-errors').addClass('hidden')
    },

    setErrors: function (html) {
      $('#appointment-errors').html(html)
    },

    setTimeSlots: function (slots) {
      $.each(slots, function (i, d) {
        $('#select_time').append(
          '<input type="button" ' +
          'class="appointment-time button button-secondary" ' +
          'name="appointment_time" ' +
          'id="gh_appointment_' + d.start + '" ' +
          'data-start_date="' + d.start + '" ' +
          'data-end_date="' + d.end + '" value="' + d.display + '"/>'
        )
      })
    },

    removeTimeSlots: function (slots) {
      $('#select_time').html('')
      //remove data form variable
      this.bookingData = {
        start_date: null,
        end_date: null,
      }
    },

    showTimeSlots: function () {
      $('#appointment-errors').addClass('hidden')
      $('.time-slots').removeClass('hidden')
    },

    hideTimeSlots: function () {
      $('.time-slots').addClass('hidden')
    },

    /**
     * @param e Node
     */
    selectAppointment: function (e) {
      // get data from hidden field
      var $e = $(e)
      this.bookingData = {
        start_date: $e.data('start_date'),
        end_date: $e.data('end_date'),
      }

      /* Remove selected class from all buttons */
      $('.appointment-time').removeClass('selected')
      $e.addClass('selected')
    }
  })

  $(function () {
    calendar.init()
  })

})(jQuery, GroundhoggCalendar)

