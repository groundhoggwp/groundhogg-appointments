(function ($, cal, gh) {

  $.fn.serializeFormJSON = function () {

    var o = {}
    var a = this.serializeArray()
    $.each(a, function () {
      if (o[this.name]) {
        if (!o[this.name].push) {
          o[this.name] = [o[this.name]]
        }
        o[this.name].push(this.value || '')
      } else {
        o[this.name] = this.value || ''
      }
    })
    return o
  }

  var CachedEvent = {}

  window.addEventListener('message', function (event) {
    if (typeof event.data.action !== 'undefined' && event.data.action === 'getFrameSize') {
      CachedEvent = event
      postSizing(event)
    }
  })

  function postSizing (event) {

    if (typeof event == 'undefined') {
      event = CachedEvent
    }
    // console.log( CachedEvent );

    var body = document.body, html = document.documentElement
    var height = Math.max(body.scrollHeight, body.offsetHeight,
      html.clientHeight, html.scrollHeight, html.offsetHeight) + 1
    var width = '100%'

    if (event.source) {
      event.source.postMessage({ height: height, width: width, id: event.data.id }, '*')
    }
  }

  $(document).on('click', function () {
    postSizing()
  })

  $.extend(cal, {

    // Jquery obnects
    wrap: null,
    date_picker: null,
    time_slots: null,
    details: null,
    form: null,
    calendar: null,

    // Spinners
    spinner: null,
    spinner_overlay: null,

    // important data
    booking_data: {},
    step: 'date',

    init: function () {

      var self = this

      this.wrap = $('#calendar-view')
      this.date_picker = $('#calendar-date-picker')
      this.time_slots = $('#calendar-time-slots')
      this.details = $('#calendar-details')
      this.form = $('#calendar-form')
      this.main = $('#main')

      this.spinner = $('.loader-wrap')
      this.spinner_overlay = $('.loader-overlay')

      this.booking_data.time_zone = jstz.determine().name()
      this.booking_data.calendar_id = this.calendar_id
      this.booking_data.reschedule = this.reschedule
      this.booking_data.action = this.appt_action

      this.init_calendar()

      $(document).on('click', '.appointment-time', function (e) {
        e.preventDefault()
        self.select_slot(e.target)
      })

      $(document).on('click', '.back-button', function (e) {
        e.preventDefault()

        switch (self.step) {
          case 'form':
            self.step = 'slots'
            break
          case 'slots':
            self.step = 'date'
            break
        }

        self.get_views()
      })

      $(document).on('submit', '.details-form', function (e) {
        e.preventDefault()

        var $form = $(this)

        self.submit($form)
      })

      self.get_views()
    },

    /* Check that appointment has been seleced */
    submit: function ($form) {

      var self = this

      this.hideErrors()

      var data = $form.serializeFormJSON()
      data._ghnonce = gh._ghnonce
      data.form_data = $form.serializeArray()
      data.action = 'groundhogg_add_appointment'
      data.booking_data = self.booking_data

      self.show_spinner()

      $.ajax({
        type: 'post',
        url: self.ajaxurl,
        dataType: 'json',
        data: data,
        success: function (response) {

          if (response.success) {

            $form.closest( '.view' ).html(response.data.message)

            if (response.data.redirect_link) {
              window.top.location.replace(response.data.redirect_link)
            }

          } else {
            $form.before(response.data.html)
          }

          postSizing()

          self.hide_spinner()

          return false
        }
      })

      return false
    },

    format_date: function (date) {
      var d = date,
        month = '' + (d.getMonth() + 1),
        day = '' + d.getDate(),
        year = d.getFullYear()

      if (month.length < 2) month = '0' + month
      if (day.length < 2) day = '0' + day

      return [year, month, day].join('-')
    },

    init_calendar: function () {
      var self = this

      this.calendar = $('#date-picker')

      self.disabled_days.push(self.max_date)

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
          if ($.inArray(self.format_date(date), self.disabled_days) !== -1) {
            return [false, '', 'unavailable']
          } else {
            return [true, '', 'available']
          }
        }
      })

      this.calendar.on('change', function (e) {

        self.booking_data.date = self.calendar.val()
        self.step = 'slots'

        self.get_views()
      })

    },

    /**
     * Get the appropriate views
     */
    get_views: function () {
      var self = this

      self.show_spinner()

      $.ajax({
        type: 'post',
        url: self.ajaxurl,
        dataType: 'json',

        data: {
          action: 'groundhogg_calendar_get_views',
          booking_data: self.booking_data,
          step: self.step,
          calendar: self.calendar_id,
        },

        success: function (response) {

          if (typeof response.success !== 'undefined' && response.success === true) {

            var classes = response.data.classes

            self.wrap.attr('class', classes.join(' '))

            var views = response.data.views

            Object.keys(views).forEach(function (key) {
              self[key].html(views[key])
            })

            self.hide_spinner()

          }

          postSizing()
        }
      })
    },

    /**
     * Select the clicked appt slot
     *
     * @param e
     */
    select_slot: function (e) {
      // get data from hidden field

      var $e = $(e)

      this.booking_data.start_time = $e.data('start_date')
      this.booking_data.end_time = $e.data('end_date')

      /* Remove selected class from all buttons */
      $('.appointment-time').removeClass('selected')
      $e.addClass('selected')

      this.step = 'form'
      this.get_views()
    },

    show_spinner: function () {
      this.spinner.show()
      this.spinner_overlay.show()
    },

    hide_spinner: function () {
      this.spinner.hide()
      this.spinner_overlay.hide()
    },

    hideErrors: function () {
      $('.gh-form-errors-wrapper').remove()
    },

    setErrors: function (html) {
      $('.gh-calendar-form').after(html)
    },

  })

  $(function () {
    cal.init()
  })

})(jQuery, BookingCalendar, Groundhogg)