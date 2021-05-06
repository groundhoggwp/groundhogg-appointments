(function ($, appointments) {
  $.extend(appointments, {
    date: null,
    bookingData: null,
    init: function () {

      var self = this

      var $appointment = $( '#appointment' );

      var calendarEl = document.getElementById('calendar')

      self.fullCalendar = new FullCalendar.Calendar(calendarEl, {
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,dayGridWeek,listWeek'
        },
        initialView: 'listWeek',
        events: appointments.events,
        eventClick: function ( info ){
          $appointment.append( appointments.spinner );
          adminAjaxRequest(
            { action: 'gh_fetch_appointment', appointment: info.event.id },
            function callback (response) {
              if ( response.success ){
                $appointment.html(response.data.html);
              }
            },
          )
        }

      })

      self.fullCalendar.render()
    },
  })

  $(function () {
    appointments.init()
  })

})(jQuery, GroundhoggAppointments)

