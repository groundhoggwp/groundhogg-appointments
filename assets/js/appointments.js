(function ($, appointments) {
  $.extend(appointments, {
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
          self.appointment = info.event.extendedProps.appointment
          $appointment.append( appointments.spinner );
          adminAjaxRequest(
            { action: 'gh_fetch_appointment', appointment: info.event.id },
            function callback (response) {
              if ( response.success ){
                $appointment.html(response.data.html);
              }
            },
            function ( error ){
              console.log(error)
            }
          )
        }

      })

      self.fullCalendar.render()

      $(document).on( 'click', '#add-meeting-notes', function (){
        $appointment.append( appointments.spinner );
        var notes = $( '#admin_notes' ).val();

        adminAjaxRequest(
          { action: 'gh_update_appointment_admin_notes', appointment: self.appointment.data.uuid, admin_notes: notes },
          function callback (response) {
            if ( response.success ){
              $appointment.children().last().remove()
            }
          },
          function ( error ){
            console.log(error)
          }
        )

      } )
    },
  })

  $(function () {
    appointments.init()
  })

})(jQuery, GroundhoggAppointments)

