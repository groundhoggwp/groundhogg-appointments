( ($) => {

  const { __, sprintf } = wp.i18n

  function ApiError (message) {
    this.name = 'ApiError'
    this.message = message
  }

  ApiError.prototype = Error.prototype

  function isString (string) {
    return typeof string === 'string'
  }

  /**
   * Function to check if we clicked inside an element with a particular class
   * name.
   *
   * @param {Object} e The event
   * @param {String} selector The class name to check against
   * @return {Boolean}
   */
  function clickedIn (e, selector) {
    var el = e.tagName ? e : e.srcElement || e.target

    if (el && el.matches(selector)) {
      return el
    }
    else {
      while (el = el.parentNode) {
        if (typeof el.matches !== 'undefined' && el.matches(selector)) {
          return el
        }
      }
    }

    return false
  }

  /**
   * If it's not a string just return the value
   *
   * @param string
   * @returns {*}
   */
  const specialChars = (string) => {
    if (!isString(string)) {
      return string
    }

    return string.replace(/&/g, '&amp;').replace(/>/g, '&gt;').replace(/</g, '&lt;').replace(/"/g, '&quot;')
  }

  /**
   * Fetch stuff from the API
   * @param route
   * @param params
   * @param opts
   */
  async function apiGet (route, params = {}, opts = {}) {

    const response = await fetch(route + '?' + $.param(params), {
      headers: {
        'X-WP-Nonce': Groundhogg.nonces._wprest,
      },
      ...opts,
    })

    let json = await response.json()

    if (!response.ok) {
      throw new ApiError(json.message)
    }

    return json
  }

  /**
   * Post data
   *
   * @param url
   * @param data
   * @param opts
   * @returns {Promise<any>}
   */
  async function apiPost (url = '', data = {}, opts = {}) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': Groundhogg.nonces._wprest,
      },
      body: JSON.stringify(data),
      ...opts,
    })

    let json = await response.json()

    if (!response.ok) {
      throw new ApiError(json.message)
    }

    return json
  }

  /**
   * Post data
   *
   * @param url
   * @param data
   * @param opts
   * @returns {Promise<any>}
   */
  async function apiDelete (url = '', data = {}, opts = {}) {
    const response = await fetch(url, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': Groundhogg.nonces._wprest,
      },
      body: JSON.stringify(data),
      ...opts,
    })

    let json = await response.json()

    if (!response.ok) {
      throw new ApiError(json.message)
    }

    return json
  }

  /**
   * Post data
   *
   * @param url
   * @param data
   * @param opts
   * @returns {Promise<any>}
   */
  async function apiPatch (url = '', data = {}, opts = {}) {
    const response = await fetch(url, {
      ...opts,
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': Groundhogg.nonces._wprest,
      },
      body: JSON.stringify(data),
    })

    let json = await response.json()

    if (!response.ok) {
      throw new ApiError(json.message)
    }

    return json
  }

  let availability = []
  let apiResponse = {}

  const appointment = {
    start: '',
    notes: '',
    email: '',
    phone: '',
    name: '',
    timezone: jstz.determine().name(),
    ...GroundhoggCalendar.contact,
  }

  const icons = {
    // language=HTML
    clock: `
        <svg viewBox="0 0 443.3 443.3" width="512" xmlns="http://www.w3.org/2000/svg">
            <path fill="currentColor"
                  d="M221.6 0C99.4 0 0 99.4 0 221.6s99.4 221.7 221.6 221.7 221.7-99.4 221.7-221.7S343.9 0 221.6 0zm0 415.6c-106.9 0-193.9-87-193.9-194s87-193.9 194-193.9 193.9 87 193.9 194-87 193.9-194 193.9z"/>
            <path fill="currentColor" d="M235.5 83.1h-27.7v144.3l87.2 87.2 19.6-19.6-79.1-79z"/>
        </svg>`,
    // language=HTML
    timezone: `
        <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
            <path fill="currentColor"
                  d="M256 0C115 0 0 115 0 256s115 256 256 256c1-.2 100.6 5 180.8-75.2C485.3 388.3 512 324.1 512 256s-26.7-132.3-75.2-180.8C355.8-6 257.5.3 256 0zm-76 43.1a254.3 254.3 0 0 0-39 77.9H75a227 227 0 0 1 105-77.9zM56 151h76.6a460 460 0 0 0-11.4 90H30.5c2.1-32.3 11-62.8 25.4-90zM30.4 271h90.7c1 31.5 4.8 61.9 11.4 90H55.9a224.3 224.3 0 0 1-25.4-90zM75 391h66a254.3 254.3 0 0 0 39.2 77.9A227 227 0 0 1 74.9 391zm166 88.4c-33.6-11.3-56.5-55-68.5-88.4H241zm0-118.4h-77.6a418 418 0 0 1-12.1-90H241zm0-120h-89.8c1-31.8 5.2-62.3 12.2-90H241zm0-120h-68.5c12-33.4 34.9-77 68.5-88.4zm240.5 150a225 225 0 0 1-55 133.2l-21-21-21.2 21.3 21 21a225 225 0 0 1-134.3 56V292.2l64.4 64.4 21.2-21.2-79.4-79.4 49.4-49.4-21.2-21.2-34.4 34.4V30.5a225 225 0 0 1 134.3 56l-21 21 21.2 21.2 21-21a225 225 0 0 1 55 133.3H452v30h29.5z"/>
        </svg>`,
    // language=HTML
    calendar: `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" xml:space="preserve">
  <path fill="currentColor"
        d="M452 40h-24V0h-40v40H124V0H84v40H60C27 40 0 67 0 100v352c0 33 27 60 60 60h392c33 0 60-27 60-60V100c0-33-27-60-60-60zm20 412c0 11-9 20-20 20H60c-11 0-20-9-20-20V188h432v264zm0-304H40v-48c0-11 9-20 20-20h24v40h40V80h264v40h40V80h24c11 0 20 9 20 20v48z"/>
            <path fill="currentColor"
                  d="M76 230h40v40H76zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zM76 310h40v40H76zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zM76 390h40v40H76zm80 0h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zm80-80h40v40h-40z"/>
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

  const changeTimeZone = (el, {
    onChange = (tz) => {},
  }) => {

    const $el = $(el)

    let search = ''

    const { timezones } = GroundhoggCalendar

    const renderTzList = () => {
      return Object.keys(timezones).filter(tz => timezones[tz].match(new RegExp(search, 'i'))).
        map(tz => `<li><button class="gh-button ${ tz === appointment.timezone
          ? 'primary'
          : 'secondary text' } tz-choice" data-tz="${ tz }">${ timezones[tz] }</button></li>`).
        join('')
    }

    const render = () => {
      // language=HTML
      return `
          <div class="timezone-modal">
              <div class="gh-panel" tabindex="0">
                  <div class="gh-panel-header">
                      <h2 style="width: 100%">
                          <input type="text" placeholder="${ __('Search...', 'groundhogg') }" class="gh-input"
                                 id="search-timezones" value="${ specialChars(search) }" autocomplete="off">
                      </h2>
                  </div>
                  <div class="tz-list">
                      <ul>
                          ${ renderTzList() }
                      </ul>
                  </div>
              </div>
          </div>`
    }

    $(el).html(render())

    const selectTZs = () => {
      $('.tz-choice').on('click', e => {

        let tz = e.target.dataset.tz

        onChange(tz)

        close()
      })
    }

    const close = () => {
      $el.html('')
      document.removeEventListener('click', clickListener)
    }

    const clickListener = e => {

      if (!clickedIn(e.target, '.timezone-modal')) {
        close()
      }

      console.log('checked')
    }

    document.addEventListener('click', clickListener)

    $('#search-timezones').on('input', e => {
      search = e.target.value
      $('.tz-list ul').html(renderTzList())
      selectTZs()
    }).focus()

    selectTZs()

  }

  const tzOnMount = () => {
    $('#change-tz').on('click', e => {
      e.stopPropagation()
      changeTimeZone('#tz-dialog', {
        onChange: tz => {
          appointment.timezone = tz
          Page.mount()
        },
      })
    })
  }

  const detailsTemplate = () => {

    let { avatar, owner_name, calendar, logo, appointment_length, timezones, description, locale } = GroundhoggCalendar

    const previousTime = () => {

      let prevTime = new Date()
      prevTime.setTime(GroundhoggCalendar.appointment.start * 1000)

      // language=HTML
      return `
          <ul class="prev appointment-details">
              <li><b style="padding-left: 0">${ __('Current date & time') }</b></li>
              <li class="item">${ icons.calendar } <span>${ prevTime.toLocaleDateString(
                      locale, {
                          weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                          hour: 'numeric',
                          minute: '2-digit',
                          timeZone: appointment.timezone,
                      }) }</span></li>
          </ul>`
    }

    // language=HTML
    return `
        <div class="calendar-details display-flex column">
            <div class="non-scrolling">

                ${ logo ? `<div id="logo-wrap" class="logo-wrap">
                    <img id="logo" class="logo" src="${ logo[0] }"/>
                </div><div class="avatar-wrap">${ avatar }</div>` : `<div class="no-logo">${ avatar }</div>` }
                <h4 class="owner-name">${ owner_name }</h4>
                <h1 class="calendar-name">${ calendar.data.name }</h1>
                <ul class="appointment-details">
                    ${ GroundhoggCalendar.appointment ? `<li><b style="padding-left: 0">${ __(
                            'New date & time') }</b></li>` : '' }
                    <li class="item">${ icons.clock } <span>${ appointment_length }</span></li>
                    ${ appointment.start
                            ? `<li class="item">${ icons.calendar } <span>${ appointment.start.toLocaleDateString(
                                    locale, {
                                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                                        hour: 'numeric',
                                        minute: '2-digit',
                                        timeZone: appointment.timezone,
                                    }) }</span></li>`
                            : '' }
                    <li>${ icons.timezone }
                        <button class="gh-button secondary text icon" id="change-tz" style="gap: 10px;">
                            ${ timezones[appointment.timezone] } ${ icons.dropdown }
                        </button>
                    </li>
                </ul>
            </div>
            <div id="tz-dialog"></div>
            ${ GroundhoggCalendar.appointment ? previousTime() : '' }
            <div class="description">
                <div class="description-inner">
                    ${ description }
                </div>
            </div>
        </div>`
  }

  const getAvailability = () => {

    if (availability.length) {
      return new Promise((resolve) => resolve())
    }

    return apiGet(`${ GroundhoggCalendar.routes.calendars }/${ GroundhoggCalendar.calendar.ID }/availability`, {
      timezone: appointment.timezone,
    }).then(r => {

      availability = r.slots

      if (appointment.start) {
        let unix = appointment.start.getTime() / 1000
        if (!availability.some(s => s.start == unix)) {
          appointment.start = false
        }
      }
    })
  }

  const pages = [
    {
      slug: /pick/,
      bodyClass: 'calendar',
      render: () => {

        // language=HTML
        return `
            <div class="display-flex gap-20 stretch">
                ${ detailsTemplate() }
                <div class="booking">
                    <p><b>${ __('Select a Date & Time', 'groundhogg-calendar') }</b></p>
                    <div id="date-picker"></div>
                </div>
            </div>
        `
      },
      onMount: ({ setPage, setParams, path }) => {

        tzOnMount()

        getAvailability().then(() => {
          GroundhoggPicker('#date-picker', {
            selected: appointment.start,
            timeZone: appointment.timezone,
            start_of_week: 0,
            availability,
            onSelectTime: (d) => {
              appointment.start = d
              setPage('/book/', {
                date: d.toISOString(),
              })
            },
            onSelectDate: (d) => {
              setParams({
                date: d.toISOString(),
              })
              resize()
            },
          })
        })

      },
    },
    {
      slug: /^\/?book\/?$/,
      bodyClass: 'form',
      beforeMount: ({
        url,
        setPage,
      }) => {
        if (!appointment.start) {

          let date = url.searchParams.get('date')

          if (date) {
            appointment.start = new Date(date)
          }
          else {
            setPage('/pick/')
          }
        }
      },
      render: () => {
        // language=HTML
        return `
            <div class="display-flex gap-20 stretch">
                ${ detailsTemplate() }
                <div id="details-form">
                    <button id="change-selection" class="gh-button secondary text icon gap-10 display-flex">
                        ${ icons.arrowLeftAlt }
                        <span>${ __('Select a different time') }</span>
                    </button>
                    <p><b>${ __('Enter Details', 'groundhogg-calendar') }</b></p>
                    <form id="submit-form">
                        <div class="gh-rows-and-columns">
                            <div class="gh-row">
                                <div class="gh-col">
                                    <label for="name">${ __('Your Name') } <span class="required">*</span></label>
                                    <input id="name" type="text" class="gh-input"
                                           value="${ specialChars(appointment.name) }" required>
                                </div>
                            </div>
                            <div class="gh-row">
                                <div class="gh-col">
                                    <label for="email">${ __('Your Email Address') } <span
                                            class="required">*</span></label>
                                    <input id="email" type="email" class="gh-input"
                                           value="${ specialChars(appointment.email) }" required>
                                </div>
                            </div>
                            <div class="gh-row">
                                <div class="gh-col">
                                    <label for="phone">${ __('Your Phone #') } <span class="required">*</span></label>
                                    <input id="phone" type="tel" class="gh-input"
                                           value="${ specialChars(appointment.phone) }" required>
                                </div>
                            </div>
                            <div class="gh-row">
                                <div class="gh-col">
                                    <label for="notes">${ __(
                                            'Please share anything that will help prepare for our meeting.') }</label>
                                    <textarea id="notes" class="gh-input" rows="3">${ specialChars(
                                            appointment.notes) }</textarea>
                                </div>
                            </div>
                        </div>
                        <button id="confirm-booking" class="gh-button primary medium">${ __('Book Now!') }</button>
                    </form>
                </div>
            </div>
        `
      },
      onMount: ({
        setPage,
        url,
      }) => {

        tzOnMount()

        $('#name,#email,#phone,#notes').on('change', e => {
          appointment[e.target.id] = e.target.value
        })

        $('#submit-form').on('submit', e => {
          e.preventDefault()

          apiPost(`${ GroundhoggCalendar.routes.calendars }/${ GroundhoggCalendar.calendar.ID }/schedule`, {
            ...appointment,
            start: appointment.start.getTime() / 1000,
          }).then(r => {
            apiResponse = r
            setPage('/confirm/')
          })
        })

        $('#change-selection').on('click', e => {
          setPage('/pick/')
        })
      },
    },
    {
      slug: /^\/?confirm\/$/,
      bodyClass: 'confirmation',
      render: (params) => {

        let { logo, owner_name, avatar, locale, timezones, appointment_length } = GroundhoggCalendar
        let { message = '', links = {} } = apiResponse

        // language=HTML
        return `
            <div class="display-flex column">
                ${ logo ? `<div id="logo-wrap" class="logo-wrap">
                    <img id="logo" class="logo" src="${ logo[0] }"/>
                </div><div class="avatar-wrap">${ avatar }</div>` : `<div class="no-logo">${ avatar }</div>` }
                <div class="confirmed">
                    <div class="display-flex gap-20">
                        <div style="width: 300px;">
                            <h2>${ __('Booking confirmed!', 'groundhogg-calendar') }</h2>
                            <p>${ sprintf(__('You are scheduled with %s.', 'groundhogg'), owner_name) }</p>
                            <p>${ sprintf(__('A calendar invitation has been send to %s', 'groundhogg-calendar'),
                                    `<b>${ appointment.email }</b>`) }</p>
                        </div>
                        <ul class="appointment-details">
                            <li class="item">${ icons.clock } <span>${ appointment_length }</span></li>
                            ${ appointment.start
                                    ? `<li class="item">${ icons.calendar } <span>${ appointment.start.toLocaleDateString(
                                            locale, {
                                                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                                                hour: 'numeric',
                                                minute: '2-digit',
                                                timeZone: appointment.timezone,
                                            }) }</span></li>`
                                    : '' }
                            <li>${ icons.timezone }<span>${ timezones[appointment.timezone] }</span></li>
                        </ul>
                    </div>
                    <div class="display-flex gap-10">
                        <button data-link="${ links.google }" class="gh-button secondary link">
                            ${ __('Add to Google Calendar') }
                        </button>
                        <button data-link="${ links.ics }" class="gh-button secondary link">
                            ${ __('Add to iCal') }
                        </button>
                        <button data-link="${ links.ics }" class="gh-button secondary link">
                            ${ __('Add to Outlook') }
                        </button>
                    </div>
                    <div class="success-message">
                        ${ message }
                    </div>
                </div>
            </div>
        `
      },
      onMount: (params, setPage) => {
        $('.link').on('click', e => {
          window.open(e.target.dataset.link, '_blank')
        })
      },
    },
    {
      slug: /^\/?cancel\/?$/,
      bodyClass: 'cancel',
      beforeMount: ({
        url,
        setPage,
      }) => {

        if (!GroundhoggCalendar.appointment) {
          setPage('/pick/')
          return
        }

      },
      render: () => {
        // language=HTML
        return `
            <div class="display-flex gap-20 stretch">
                ${ detailsTemplate() }
                <div id="details-form">
                    <p><b>${ __('Cancel Booking', 'groundhogg-calendar') }</b></p>
                    <form id="cancel-form">
                        <div class="gh-rows-and-columns">
                            <div class="gh-row">
                                <div class="gh-col">
                                    <label for="notes">${ __(
                                            'Reason for cancelling?') }</label>
                                    <textarea id="notes" class="gh-input" rows="3" required></textarea>
                                </div>
                            </div>
                        </div>
                        <button id="confirm-booking" class="gh-button danger medium">${ __('Cancel') }</button>
                    </form>
                </div>
            </div>
        `
      },
      onMount: ({
        setPage,
        url,
      }) => {

        let reason

        $('#notes').on('input change', e => {
          reason = e.target.value
        })

        $('#cancel-form').on('submit', e => {
          e.preventDefault()

          apiDelete(`${ GroundhoggCalendar.routes.appointments }/${ GroundhoggCalendar.appointment.uuid }`, {
            reason,
          }).then(r => {
            setPage('/cancelled/')
          })
        })

      },
    },
    {
      slug: /^\/?cancelled\/$/,
      bodyClass: 'cancelled',
      render: () => {

        let { logo, owner_name, avatar, locale, timezones, appointment_length } = GroundhoggCalendar

        // language=HTML
        return `
            <div class="display-flex column">
                ${ logo ? `<div id="logo-wrap" class="logo-wrap">
                    <img id="logo" class="logo" src="${ logo[0] }"/>
                </div><div class="avatar-wrap">${ avatar }</div>` : `<div class="no-logo">${ avatar }</div>` }
                <div class="confirmed">
                    <h2>${ __('Booking Cancelled', 'groundhogg-calendar') }</h2>
                    <p>${ sprintf(__('Your meeting with %s has been cancelled.', 'groundhogg'), owner_name) }</p>
                </div>
            </div>
        `
      },
      onMount: (params, setPage) => {

      },
    },
    {
      slug: /^\/?reschedule\/?$/,
      bodyClass: 'calendar',
      render: () => {

        // language=HTML
        return `
            <div class="display-flex gap-20 stretch">
                ${ detailsTemplate() }
                <div class="booking">
                    <p><b>${ __('Select a new Date & Time', 'groundhogg-calendar') }</b></p>
                    <div id="date-picker"></div>
                </div>
            </div>
        `
      },
      onMount: ({ setPage, setParams, path }) => {

        tzOnMount()

        getAvailability().then(() => {
          GroundhoggPicker('#date-picker', {
            selected: appointment.start,
            timeZone: appointment.timezone,
            start_of_week: 0,
            availability,
            onSelectTime: (d) => {
              appointment.start = d
              setPage('/reschedule/book/', {
                date: d.toISOString(),
              })
            },
            onSelectDate: (d) => {
              setParams({
                date: d.toISOString(),
              })
              resize()
            },
          })
        })

      },
    },
    {
      slug: /^\/?reschedule\/book\/?$/,
      bodyClass: 'reschedule',
      render: () => {
        // language=HTML
        return `
            <div class="display-flex gap-20 stretch">
                ${ detailsTemplate() }
                <div id="details-form">
                    <p><b>${ __('Reschedule Booking', 'groundhogg-calendar') }</b></p>
                    <form id="reschedule-form">
                        <div class="gh-rows-and-columns">
                            <div class="gh-row">
                                <div class="gh-col">
                                    <label for="notes">${ __(
                                            'Reason for rescheduling?') }</label>
                                    <textarea id="notes" class="gh-input" rows="3" required></textarea>
                                </div>
                            </div>
                        </div>
                        <button id="confirm-booking" class="gh-button primary medium">${ __('Reschedule') }</button>
                    </form>
                </div>
            </div>
        `
      },
      onMount: ({
        setPage,
        url,
      }) => {

        let reason

        $('#notes').on('input change', e => {
          reason = e.target.value
        })

        $('#reschedule-form').on('submit', e => {
          e.preventDefault()

          apiPatch(`${ GroundhoggCalendar.routes.appointments }/${ GroundhoggCalendar.appointment.uuid }`, {
            start: appointment.start.getTime() / 1000,
            reason,
          }).then(r => {
            apiResponse = r
            setPage('/confirm/')
          })
        })

      },
    },
  ]

  const resize = () => {
    $('#calendar')[0].dispatchEvent(new Event('resize'))
  }

  const Page = {

    slug: '',
    page: pages[0],
    path: [],
    url: null,

    getHash () {
      return this.url.hash.substring(1)
    },

    initFromHash () {
      this.slug = this.getHash()
      this.path = this.slug.split('/').filter(p => p)
      this.mount()
    },

    init () {

      this.url = new URL(window.location.href)

      if (this.url.hash) {
        this.initFromHash()
      }
      else {
        this.url.hash = `#/pick/`
        history.pushState({}, '', this.url)
        this.initFromHash()
      }

      window.addEventListener('popstate', (e) => {
        this.url = new URL(window.location.href)
        this.initFromHash()
      })
    },

    setPage (hash, params) {
      this.url.hash = `#${ hash }`
      this.setParams(params)
      this.initFromHash()
    },

    setParams (params) {
      for (let param in params) {
        this.url.searchParams.set(param, params[param])
      }
      history.pushState({}, '', this.url)
    },

    mount () {

      this.page = pages.find(p => this.slug.match(p.slug))

      const setPage = (hash, params = {}) => this.setPage(hash, params)
      const setParams = (params = {}) => this.setParams(params)

      try {
        this.page.beforeMount({
          url: this.url,
          path: this.path,
          setPage,
          setParams,
        })
      }
      catch (e) {}

      $('#calendar').html(this.page.render({
        url: this.url,
        path: this.path,
      }))

      this.page.onMount({
        url: this.url,
        path: this.path,
        setPage,
        setParams,
      })

      $('body').addClass(this.page.bodyClass)
      resize()
    },

  }

  $(() => Page.init())

} )(jQuery)
