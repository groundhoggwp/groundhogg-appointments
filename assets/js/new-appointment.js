(function ($, calendar) {
  $.extend(calendar, {
    from: '',
    to: '',
    contact: 0,
    init: function () {

      var self = this

      self.renderCalendar()

      $('#calendar-id').on('change', function () {
        var calendarId = $(this).val()
        self.fetchCalendar(calendarId)
      })

      $(document).on('click', '.appointment-time', function () {

        var $chosen = $(this)

        $('.appointment-time').removeClass('chosen')
        $chosen.addClass('chosen')

        self.from = $chosen.data('from')
        self.to = $chosen.data('to')

        self.maybeDisableButton();
      })

      $('#contact-id').on('change', function () {
        self.contact_id = $(this).val()
        self.maybeDisableButton();
      })

      $('#schedule').on('click', function (e) {
        self.schedule()
      })
    },

    schedule: function () {

      var self = this

      self.showLoader()

      adminAjaxRequest(
        {
          action: 'gh_schedule_new_appointment',
          from: self.from,
          to: self.to,
          contact_id: self.contact_id,
          calendar_id: self.calendar.ID,
        },
        function (response) {
          window.location.replace(response.data.redirect)
        },
        function (err) {
          console.log(err)
        }
      )
    },

    renderCalendar: function () {

      var self = this

      if (!self.config) {
        return
      }

      $('#calendar').datepicker({
        firstDay: self.datepicker.start_of_week,
        minDate: self.config.min_date,
        maxDate: self.config.max_date,
        changeMonth: false,
        changeYear: false,
        dateFormat: 'yy-mm-dd',
        // dayNamesMin: self.datepicker.day_names,
        onSelect: function (time, picker) {
          self.date = time
          self.refreshTimeSlots()
        },
        beforeShowDay: function (date) {
          // console.debug(date)
          if ($.inArray(self.formatDate(date), self.config.disabled_days) !== -1) {
            return [false, '', 'unavailable']
          } else {
            return [true, '', 'available']
          }
        }
      })

    },

    fetchCalendar: function (id) {

      var self = this

      this.showLoader()

      adminAjaxRequest(
        { action: 'gh_fetch_calendar_config', calendar: id },
        function (response) {

          self.calendar = response.data.calendar
          self.config = response.data.config

          self.renderCalendar()
          self.refreshTimeSlots()
        },
        function (err) {
          console.log(err)
        },
      )
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
      calendar.from = null
      calendar.to = null
    },

    refreshTimeSlots: function () {

      var self = this
      this.showLoader()

      adminAjaxRequest(
        { action: 'gh_get_appointment_slots', date: self.date, calendar_id: self.calendar.ID },
        function (response) {
          $('#slots').html(response.data.html)
          self.hideLoader()
        },
        function (err) {
          console.log(err)
        },
      )
    },

    hideLoader () {
      $('#loader-wrap').addClass('hidden')
    },

    showLoader () {
      $('#loader-wrap').removeClass('hidden')
    },

    maybeDisableButton(){
      $('#schedule').prop( 'disabled', ! this.from || ! this.to || ! this.calendar || ! this.date || ! this.contact_id )
    }

  })

  $(function () {
    calendar.init()
  })

})(jQuery, GroundhoggAppointments)

