( ($, appointments) => {

  const {
    modal,
    icons,
    dangerConfirmationModal,
    confirmationModal,
    textarea,
    miniModal,
    loadingModal,
    input,
    dialog,
    bold,
  } = Groundhogg.element

  const { formatDate } = Groundhogg.formatting
  const { emailModal, quickEditContactModal } = Groundhogg.components
  const { get, patch, post, delete: _delete } = Groundhogg.api
  const { createFilters } = Groundhogg.filters.functions

  // const AppointmentsStore =

  const { __, _x, sprintf } = wp.i18n

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

  const calendar = (el, {
    selected = false,
    start_of_week = 0,
    availability = [],
    onSelectDate = (date) => {},
    onSelectTime = (date) => {},
  }) => {

    let year, month, slotScroll, time

    availability.sort((a, b) => a.start - b.start)
    let initDate = new Date(availability[0].start * 1000)
    initDate.setDate(1)

    year = initDate.getFullYear()
    month = initDate.getMonth()

    if (start_of_week) {
      let add_to_end = days_of_week.splice(0, start_of_week)
      days_of_week.push(...add_to_end)
    }

    const render = () => {

      let currDate = new Date(year, month, 1)

      let week = 0
      let calCells = [[]]
      let timeSlots

      if (currDate.getDay() !== start_of_week) {
        let padding = Math.abs(start_of_week - currDate.getDay())
        for (let i = 0; i < padding; i++) {
          calCells[week].push('')
        }
      }

      while (currDate.getMonth() < month + 1) {
        calCells[week].push({
          selected: selected && selected.toISOString().split('T')[0] === currDate.toISOString().split('T')[0],
          date: currDate.getDate(),
          month: currDate.getMonth(),
          year: currDate.getFullYear(),
          obj: new Date(currDate),
        })
        if (calCells[week].length === 7) {
          week++
          calCells.push([])
        }
        currDate.setDate(currDate.getDate() + 1)
      }

      if (selected) {
        let unix = selected.getTime() / 1000
        timeSlots = availability.filter(s => s.start >= unix && s.start < ( unix + ( 3600 * 24 ) ))
      }

      const timeUI = () => {

        const getTime = (s) => {
          let date = new Date()
          date.setTime(s.start * 1000)
          return date.toLocaleTimeString(GroundhoggCalendar.locale, {
            timeZone: appointment.timezone,
            hour: '2-digit', minute: '2-digit',
          })
        }

        // language=HTML
        return `
            <div class="display-flex column">
                <button id="change-date" class="gh-button secondary text icon gap-10 display-flex">
                    ${ icons.arrowLeftAlt }
                    <span>${ __('Select a different date') }</span>
                </button>
                <span class="display-date">${ selected.toDateString() }</span>

                <div id="time-slots">
                    <div class="time-slot-wrap">
                        ${ timeSlots.map(
                                s => s.start == time
                                        ? `<div class="slot-wrap display-flex gap-10"><div class="time-selected"><b>${ getTime(
                                                s) }</b></div><button class="gh-button primary" id="confirm"><b>${ __(
                                                'Confirm') }</b></button></div>`
                                        : `<div class="slot-wrap"><button class="gh-button secondary select-slot" data-start="${ s.start }"><b>${ getTime(
                                                s) }</b></button></div>`).
                                join('') }
                    </div>

                </div>
            </div>`
      }

      // language=HTML
      return `
          <div class="gh-calendar display-flex ${ selected ? 'date-selected' : '' }">
              <div class="date-picker">
                  <div class="date-picker-header display-flex space-between">
                      <span class="display-date">${ months[month] } ${ year }</span>
                      <div class="display-flex">
                          <button id="prev-month" class="gh-button primary text icon"
                                  ${ availability.some(s => s.month == month - 1) ? '' : 'disabled' }>
                              ${ icons.arrowLeft }
                          </button>
                          <button id="next-month" class="gh-button primary text icon"
                                  ${ availability.some(s => s.month == month + 1) ? '' : 'disabled' }>
                              ${ icons.arrowRight }
                          </button>
                      </div>
                  </div>
                  <table class="gh-calendar-table">
                      <thead>
                      <tr>
                          ${ days_of_week.map(d => `<th>${ d }</th>`).join('') }
                      </tr>
                      </thead>
                      <tbody>
                      ${ calCells.map(week => `<tr>${ week.map(d => d
                              ? ( availability.some(s => s.start >= ( d.obj.getTime() / 1000 ) && s.start <
                                      ( ( d.obj.getTime() / 1000 ) + ( 24 * 60 * 60 ) ))
                                      ? `<td><button class="date-choice gh-button ${ d.selected
                                              ? 'primary'
                                              : 'secondary' }" data-year="${ d.year }" data-date="${ d.date }" data-month="${ d.month }" type="button"><b>${ d.date }</b></button></td>`
                                      : `<td class="disabled">${ d.date }</td>` )
                              : '<td></td>').join('') }</tr>`).join('') }
                      </tbody>
                  </table>
              </div>
              ${ selected ? timeUI() : '' }
          </div>`
    }

    const mount = () => {
      $(el).html(render())
      onMount()
    }

    const onMount = () => {

      $('#change-date').on('click', e => {
        selected = false
        mount()
      })

      $('#time-slots').on('scroll', e => {
        slotScroll = $(e.target).scrollTop()
      }).scrollTop(slotScroll)

      $('#confirm').on('click', e => {
        // Modify selected to the desired time
        selected.setTime(time * 1000)
        onSelectTime(selected)
      })

      $('.select-slot').on('click', e => {

        time = e.currentTarget.dataset.start
        mount()

      })

      $('.date-choice').on('click', e => {

        let d = e.currentTarget.dataset
        selected = new Date(d.year, d.month, d.date, 0, 0, 0)

        mount()
        onSelectDate(selected)

      })

      $('#next-month').on('click', e => {

        month++
        mount()

      })

      $('#prev-month').on('click', e => {
        month--
        mount()
      })

    }

    mount()

  }

  let fullCalendar

  const getAvailability = ({
    calendar,
  }) => {
    return get(`${ GroundhoggAppointments.routes.calendars }/${ calendar }/availability`).then(r => r.slots)
  }

  const displayAppointment = (_def) => {

    let { title, extendedProps } = _def
    let { appointment } = extendedProps
    let { i18n, contact } = appointment

    modal({
      //language=HTML
      content: `
          <div class="gh-header">
              <h3 style="font-weight: normal">${sprintf( __('%s and %s'), bold( contact.data.full_name ), bold( i18n.ownerName ) )}</h3>
              <div class="display-flex">
                  <div class="display-flex align-center">
                      ${ appointment.data.status === 'cancelled'
                              ? `<i>${__('This appointment was cancelled and cannot be changed.')}</i><button id="delete" class="gh-button danger text">${ __('Delete') }</button>`
                              : `<button id="reschedule" class="gh-button primary text">${ __('Reschedule') }</button>
                      <button id="cancel" class="gh-button danger text">${ __('Cancel') }</button>` }
                  </div>
                  <button id="close-appt" class="gh-button secondary icon text">
                      <span class="dashicons dashicons-no-alt"></span>
                  </button>
              </div>
          </div>
          <div class="display-flex gap-20">
              <div id="details">
                  <h3><b>${ __('Contact') }</b></h3>
                  <div class="display-flex">
                      <img class="avatar" style="margin-right: 20px" src="${ contact.data.gravatar }">
                      <div style="width: 100%">
                          <div><b>${ contact.data.full_name }</b></div>
                          <div><a href="#">${ contact.data.email }</a></div>
                      </div>
                      ${ contact.meta.primary_phone
                              ? `<a id="call" class="gh-button secondary text icon" href="tel:${ contact.meta.primary_phone }">${ icons.phone }</a>`
                              : '' }
                      <button id="send-email" class="gh-button secondary text icon">${ icons.email }</button>
                      <button id="contact-more" class="gh-button secondary text icon">${ icons.verticalDots }</button>
                  </div>
                  <h3><b>${ __('Details') }</b></h3>
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
              </div>
              <div id="notes-here"></div>
          </div>`,
      width: 800,
      onOpen: ({ close, setContent }) => {

        $('#close-appt').on('click', () => close())

        $('#reschedule').on('click', e => {

          let date, reason

          modal({
            content: `<div id="date-picker"></div>`,
            onOpen: ({ close: closePicker, setContent }) => {

              getAvailability({
                calendar: appointment.data.calendar_id,
              }).then(availability => {
                GroundhoggPicker('#date-picker', {
                  start_of_week: 0,
                  availability,
                  onSelectTime: (d) => {

                    date = d
                    console.log(d)

                    closePicker()

                    confirmationModal({
                      //language=HTML
                      alert: `<p>
                          ${ __('Are you sure you want to reschedule this appointment?', 'groundhogg-calendar') }</p>
                      <p>${ textarea({
                          placeholder: __('Reason for rescheduling...'),
                          className: 'full-width cancel-reason',
                          id: 'reschedule-reason',
                      }) }</p>`,
                      confirmText: __('Reschedule'),
                      onConfirm: () => {
                        let { close: closeLoading } = loadingModal()
                        patch(`${ GroundhoggAppointments.routes.appointments }/${ appointment.data.uuid }`, {
                          start: date,
                          reason,
                        }).then(() => {
                          closeLoading()
                          close()
                          fullCalendar.refetchEvents()
                          dialog({
                            message: __('Appointment reschedule!'),
                          })
                        })
                      },
                      onOpen: () => {
                        $('#reschedule-reason').on('input', e => {
                          reason = e.target.value
                        })
                      },
                    })
                  },
                  onSelectDate: (d) => {},
                })
              })

            },
          })

        })

        $('#cancel').on('click', () => {

          let reason = ''

          dangerConfirmationModal({
            //language=HTML
            alert: `<p>${ __('Are you sure you want to cancel this appointment?', 'groundhogg-calendar') }</p>
            <p>${ textarea({
                placeholder: __('Reason for cancelling...'),
                className: 'full-width cancel-reason',
                id: 'cancel-reason',
            }) }</p>`,
            confirmText: __('Cancel'),
            closeText: __('Nevermind'),
            onConfirm: () => {
              let { close: closeLoading } = loadingModal()
              _delete(`${ GroundhoggAppointments.routes.appointments }/${ appointment.data.uuid }`, {
                reason,
              }).then(() => {
                closeLoading()
                close()
                fullCalendar.refetchEvents()
                dialog({
                  message: __('Appointment cancelled!'),
                })
              })
            },
            onOpen: () => {
              $('#cancel-reason').on('input', e => {
                reason = e.target.value
              })
            },
          })

        })

        $('#delete').on('click', () => {

          dangerConfirmationModal({
            //language=HTML
            alert: `<p>${ __('Are you sure you want to delete this appointment?', 'groundhogg-calendar') }</p>`,
            confirmText: __('Delete'),
            closeText: __('Cancel'),
            onConfirm: () => {
              let { close: closeLoading } = loadingModal()
              _delete(`${ GroundhoggAppointments.routes.appointments }/${ appointment.ID }` ).then(() => {
                closeLoading()
                close()
                fullCalendar.refetchEvents()
                dialog({
                  message: __('Appointment deleted!'),
                })
              })
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

  const render = () => {
    // language=html
    return `
        <div class="gh-header">
            <div class="title-wrap">
                <h1 class="breadcrumbs">${ __('Appointments') }</h1>
            </div>
            <div class="display-flex gap-10">
                <button class="gh-button icon text secondary" id="quick-search">
                    <span class="dashicons dashicons-search"></span>
                </button>
                <button class="gh-button icon text secondary" id="filters">
                    <span class="dashicons dashicons-filter"></span>
                </button>
                <button class="gh-button secondary text icon" id="schedule">
                    <span class="dashicons dashicons-plus-alt2"></span>
                </button>
                <button class="gh-button text icon secondary" id="more-options">
                    ${ icons.verticalDots }
                </button>
            </div>
        </div>
        <div class="display-flex" id="content-wrap">
            <div id="sidebar">
                <h2>${ __('Calendars') }</h2>
                <ul id="local-calendars-filter"></ul>
                <h2>${ __('Synced Calendars') }</h2>
                <ul id="synced-calendars-filter"></ul>
            </div>
            <div class="calendar-wrap">
                <div class="gh-panel" id="full-calendar-wrap">
                </div>
            </div>
        </div>`
  }

  const calendarFilters = (selector, {
    calendars,
    selected,
    onChange = () => {},
  }) => {

    selected = selected.map(id => parseInt(id))

    const $el = $(selector)

    const render = () => {
      // language=HTML
      return calendars.map(cal => `
          <li><label>${ input({
              type: 'checkbox',
              checked: selected.includes(parseInt(cal.ID)),
              dataId: cal.ID,
              className: 'toggle-calendar',
          }) } ${ cal.data.name }</label></li>`).join('')
    }

    $el.html(render())

    $el.on('change', '.toggle-calendar', e => {
      if (e.target.checked) {
        onChange([...selected, parseInt(e.target.dataset.id)])
      }
      else {
        onChange(selected.filter(c => c != parseInt(e.target.dataset.id)))
      }
    })

  }

  const Appointments = {

    filters: [],
    selectedCalendars: [],
    selectedSyncedCalendars: [],
    search: '',

    init () {

      this.selectedCalendars = GroundhoggAppointments.selected.local
      this.selectedSyncedCalendars = GroundhoggAppointments.selected.synced

      this.mount()
    },

    fetchAppointments ({ start, end }) {
      return get(GroundhoggAppointments.routes.appointments + '/events', {
        before: end.toISOString(),
        after: start.toISOString(),
        calendars: this.selectedCalendars,
        synced: this.selectedSyncedCalendars,
        filters: this.filters,
      })
    },

    mount () {
      $('#appointments-app').html(render())

      let calendarEl = document.getElementById('full-calendar-wrap')
      let $calendarEl = $(calendarEl)

      let initialView = 'timeGridWeek'
      let defaultDate = false

      if (fullCalendar) {
        initialView = fullCalendar.view.type
        defaultDate = fullCalendar.view.currentStart
      }

      fullCalendar = new FullCalendar.Calendar(calendarEl, {
        editable: false,
        // eventStartEditable: true,
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,listWeek',
        },
        initialView,

        events: (params, success, failure) => {
          const { close } = loadingModal()
          this.fetchAppointments(params).then((r) => {
            success(r.items)
            close()
          }).catch(e => failure(e))
        },
        height: $calendarEl.parent().height(),
        eventClick: function (info) {
          displayAppointment(info.event._def)
        },
      })

      fullCalendar.render()

      let filtersOpen = false

      $('#filters').on('click', () => {

        if (filtersOpen) {
          return
        }

        miniModal('#filters', {
          closeOnFocusout: false,
          dialogClasses: 'overflow-visible',
          // language=HTML
          content: `
              <div id="filters-here"></div>
              <button class="gh-button text danger alignright" id="clear-filters">${ __('Clear Filters', 'groundhogg') }
              </button>`,
          onOpen: () => {
            filtersOpen = true
            let fw = createFilters('#filters-here', this.filters, (filters) => {
              this.filters = filters
              this.mount()
            })

            fw.init()

            $('#clear-filters').on('click', (e) => {
              this.filters = []
              fullCalendar.refetchEvents()

            })
          },
          onClose: () => {
            filtersOpen = false
          },
        })
      })

      calendarFilters('#local-calendars-filter', {
        calendars: GroundhoggAppointments.calendars.local,
        selected: this.selectedCalendars,
        onChange: (cals) => {
          this.selectedCalendars = cals
          this.mount()
        },
      })

      calendarFilters('#synced-calendars-filter', {
        calendars: GroundhoggAppointments.calendars.synced,
        selected: this.selectedSyncedCalendars,
        onChange: (cals) => {
          this.selectedSyncedCalendars = cals
          this.mount()
        },
      })
    },

    onMount () {

    },

    hasFilters () {
      return this.filters && this.filters[0]
    },

  }

  $(() => {
    Appointments.init()
  })

} )(jQuery, GroundhoggAppointments)

