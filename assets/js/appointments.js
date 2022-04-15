( ($, appointments) => {

  const {
    modal,
    icons,
    dangerConfirmationModal,
  } = Groundhogg.element

  const { formatDate } = Groundhogg.formatting
  const { emailModal, quickEditContactModal } = Groundhogg.components

  const { __, _x } = wp.i18n

  const calIcons = {
    // language=HTML
    clock: `
        <svg viewBox="0 0 443.3 443.3" width="20" xmlns="http://www.w3.org/2000/svg">
            <path fill="currentColor"
                  d="M221.6 0C99.4 0 0 99.4 0 221.6s99.4 221.7 221.6 221.7 221.7-99.4 221.7-221.7S343.9 0 221.6 0zm0 415.6c-106.9 0-193.9-87-193.9-194s87-193.9 194-193.9 193.9 87 193.9 194-87 193.9-194 193.9z"/>
            <path fill="currentColor" d="M235.5 83.1h-27.7v144.3l87.2 87.2 19.6-19.6-79.1-79z"/>
        </svg>`,
    // language=HTML
    timezone: `
        <svg viewBox="0 0 512 512" width="20" xmlns="http://www.w3.org/2000/svg">
            <path fill="currentColor"
                  d="M256 0C115 0 0 115 0 256s115 256 256 256c1-.2 100.6 5 180.8-75.2C485.3 388.3 512 324.1 512 256s-26.7-132.3-75.2-180.8C355.8-6 257.5.3 256 0zm-76 43.1a254.3 254.3 0 0 0-39 77.9H75a227 227 0 0 1 105-77.9zM56 151h76.6a460 460 0 0 0-11.4 90H30.5c2.1-32.3 11-62.8 25.4-90zM30.4 271h90.7c1 31.5 4.8 61.9 11.4 90H55.9a224.3 224.3 0 0 1-25.4-90zM75 391h66a254.3 254.3 0 0 0 39.2 77.9A227 227 0 0 1 74.9 391zm166 88.4c-33.6-11.3-56.5-55-68.5-88.4H241zm0-118.4h-77.6a418 418 0 0 1-12.1-90H241zm0-120h-89.8c1-31.8 5.2-62.3 12.2-90H241zm0-120h-68.5c12-33.4 34.9-77 68.5-88.4zm240.5 150a225 225 0 0 1-55 133.2l-21-21-21.2 21.3 21 21a225 225 0 0 1-134.3 56V292.2l64.4 64.4 21.2-21.2-79.4-79.4 49.4-49.4-21.2-21.2-34.4 34.4V30.5a225 225 0 0 1 134.3 56l-21 21 21.2 21.2 21-21a225 225 0 0 1 55 133.3H452v30h29.5z"/>
        </svg>`,
    // language=HTML
    calendar: `
        <svg xmlns="http://www.w3.org/2000/svg" width="20" viewBox="0 0 512 512" xml:space="preserve">
  <path fill="currentColor"
        d="M452 40h-24V0h-40v40H124V0H84v40H60C27 40 0 67 0 100v352c0 33 27 60 60 60h392c33 0 60-27 60-60V100c0-33-27-60-60-60zm20 412c0 11-9 20-20 20H60c-11 0-20-9-20-20V188h432v264zm0-304H40v-48c0-11 9-20 20-20h24v40h40V80h264v40h40V80h24c11 0 20 9 20 20v48z"/>
            <path fill="currentColor"
                  d="M76 230h40v40H76zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zM76 310h40v40H76zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zM76 390h40v40H76zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zm80-80h40v40h-40z"/>
</svg>`,
    // language=HTML
    arrowRight: `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
            <path d="M79.1 0 48.9 30.2l97.8 97.8-97.8 97.8L79.1 256l128-128z"
                  fill="currentColor"/>
        </svg>`,
    // language=HTML
    arrowLeft: `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">
            <path fill="currentColor"
                  d="M207.1 30.2 176.9 0l-128 128 128 128 30.2-30.2-97.8-97.8z"/>
        </svg>`,
    // language=HTML
    dropdown: `
        <svg style="height: 10px;width: 10px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"
             xml:space="preserve">
  <path fill="currentColor" d="m0 63.8 127.5 127.5L255 63.8z"/>
</svg>`,
    // language=HTML
    arrowLeftAlt: `
        <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 9H3.8l5.6-5.6L8 2l-8 8 8 8 1.4-1.4L3.8 11H20z"/>
        </svg>`,
  }

  let fullCalendar

  const displayAppointment = (_def) => {

    let { title, extendedProps } = _def
    let { appointment } = extendedProps
    let { i18n, contact } = appointment

    modal({
      //language=HTML
      content: `
          <div class="gh-header">
              <h3>${ title }</h3>
              <div class="display-flex">
                  <button id="close-appt" class="gh-button secondary icon text">
                      <span class="dashicons dashicons-no-alt"></span>
                  </button>
              </div>
          </div>
          <div class="display-flex gap-20" style="margin-top: 40px">
              <div id="details">
                  <h3><b>${__('Contact')}</b></h3>
                  <div class="display-flex">
                      <img class="avatar" style="margin-right: 20px" src="${contact.data.gravatar}">
                      <div style="width: 100%">
                          <div><b>${contact.data.full_name}</b></div>
                          <div><a href="#">${contact.data.email}</a></div>
                      </div>
                      ${ contact.meta.primary_phone ?  `<a id="call" class="gh-button secondary text icon" href="tel:${contact.meta.primary_phone}">${icons.phone}</a>` : '' }
                      <button id="send-email" class="gh-button secondary text icon">${icons.email}</button>
                      <button id="contact-more" class="gh-button secondary text icon">${icons.verticalDots}</button>
                  </div>
                  <h3><b>${__('Details')}</b></h3>
                  <ul>
                      ${ i18n.dateFrom === i18n.dateTo ? ` <li class="display-flex gap-10">
                          ${ calIcons.calendar } ${ i18n.dateFrom } 
                      </li>` : '' }
                      <li class="display-flex gap-10">
                          ${ calIcons.clock } ${ i18n.dateFrom === i18n.dateTo
                              ? `${ i18n.from } - ${ i18n.to }`
                              : `${ i18n.dateFrom } ${ i18n.from } - ${ i18n.dateTo } ${ i18n.to }` }
                      </li>
                  </ul>
                  <div class="display-flex">
                      <button id="reschedule" class="gh-button primary text">${ __('Reschedule') }</button>
                      <button id="cancel" class="gh-button danger text">${ __('Cancel') }</button>
                  </div>
              </div>
              <div id="notes-here"></div>
          </div>`,
      width: 800,
      onOpen: ({ close, setContent }) => {

        console.log({ appointment })

        $('#close-appt').on('click', () => close())
        $('#cancel').on('click', () => {
          dangerConfirmationModal({
            alert: `<p>${__('Are you sure you want to cancel this appointment?', 'groundhogg-calendar')}</p>`,
            confirmText: __( 'Cancel' ),
            closeText: __( 'Never mind' ),
            onConfirm: () => {

            }
          })
        })

        Groundhogg.noteEditor('#notes-here', {
          object_id: appointment.ID,
          object_type: 'appointment',
        })

      },
    })

  }

  const init = () => {

    let calendarEl = document.getElementById('calendar')
    let $calendarEl = $(calendarEl)

    fullCalendar = new FullCalendar.Calendar(calendarEl, {
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,dayGridWeek,listWeek',
      },
      initialView: 'listWeek',
      events: appointments.events,
      height: $calendarEl.parent().height(),
      eventClick: function (info) {
        displayAppointment(info.event._def)
      },
    })

    fullCalendar.render()
  }

  $(() => {
    init()
  })

} )(jQuery, GroundhoggAppointments)

