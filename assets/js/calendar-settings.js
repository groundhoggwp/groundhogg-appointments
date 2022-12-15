( ($) => {

  const {
    icons,
    toggle,
    input,
    select,
    textarea,
    dialog,
    modal,
    inputRepeater,
    improveTinyMCE,
    tinymceElement,
    inputWithReplacements,
    textAreaWithReplacements,
    copyObject,
    loadingModal,
  } = Groundhogg.element

  const { __, sprintf } = wp.i18n

  const CalendarsStore = Groundhogg.createStore('calendars', GroundhoggCalendar.routes.calendars)
  const GoogleConnections = Groundhogg.createStore('google_connections', GroundhoggCalendar.routes.google_connections)
  const { options: Options } = Groundhogg.stores
  const { getOwner } = Groundhogg.user

  GoogleConnections.itemsFetched(GroundhoggCalendar.connections.google)
  CalendarsStore.itemsFetched([GroundhoggCalendar.calendar])

  improveTinyMCE()

  let dataChanges = {}

  const updateData = (data) => {
    dataChanges = {
      ...dataChanges,
      ...data,
    }
  }

  let metaChanges = {}

  const updateMeta = (meta) => {
    metaChanges = {
      ...metaChanges,
      ...meta,
    }
  }

  const getCalendar = () => {
    let calendar = copyObject(CalendarsStore.get(GroundhoggCalendar.calendar.ID))

    calendar.meta = {
      ...calendar.meta,
      ...metaChanges,
    }

    calendar.data = {
      ...calendar.data,
      ...dataChanges,
    }

    return calendar
  }

  const isSMSActive = () => GroundhoggCalendar.active.sms

  /**
   * There can be only one!
   *
   * @param items
   * @returns {any[]}
   */
  const arrayUnique = (items) => {
    let set = new Set(items)
    return [...set]
  }

  const skipButton = (text) => {
    // language=HTML
    return `
        <div class="display-flex center" style="margin-top: 40px">
            <button id="skip" class="gh-button secondary text">
                ${ text }
            </button>
        </div>`
  }

  let settings = {}

  const saveChanges = () => {
    return CalendarsStore.patch(getCalendar().ID, {
      data: {
        ...dataChanges,
      },
      meta: {
        ...metaChanges,
      },
    })
  }

  const getGoogleConnection = (ownerId) => {
    if (GroundhoggCalendar.connections.owners.hasOwnProperty(ownerId)) {
      let accountId = GroundhoggCalendar.connections.owners[ownerId].google
      return GoogleConnections.find(con => con.data.account_id === accountId)
    }

    return false
  }

  const notificationsTable = (selector, {
    notifications = {},
    onChange = () => {},
    editTemplate = () => {},
    forceEnabled = false,
  }) => {

    notifications = {
      scheduled: { enabled: false },
      rescheduled: { enabled: false },
      cancelled: { enabled: false },
      ...notifications,
    }

    const $el = $(selector)

    const render = () => {

      const row = ({
        text,
        name,
        checked,
      }) => {
        // language=HTML
        return `
            <tr>
                <td>${ text }</td>
                <td>${ toggle({
                    id: name,
                    className: 'toggle-notification',
                    name,
                    checked: checked || forceEnabled,
                    disabled: forceEnabled,
                }) }
                </td>
                <td><a data-which="${ name }" class="personalize-template">${ __('Personalize') }</a></td>
            </tr>`
      }

      //language=HTML
      return `
          <table class="notifcations">
              ${ row({
                  text: __('When a new appointment is <b>scheduled</b>'),
                  name: 'scheduled',
                  checked: notifications.scheduled.enabled,
              }) }
              ${ row({
                  text: __('When an appointment is <b>rescheduled</b>'),
                  name: 'rescheduled',
                  checked: notifications.rescheduled.enabled,
              }) }
              ${ row({
                  text: __('When an appointment is <b>cancelled</b>'),
                  name: 'cancelled',
                  checked: notifications.cancelled.enabled,
              }) }
          </table>`
    }

    const onMount = () => {

      $el.find('.toggle-notification input').on('change', e => {
        let which = e.target.name
        notifications[which].enabled = e.target.checked
        onChange(notifications)
      })

      $el.find('.personalize-template').on('click', e => {
        let which = e.target.dataset.which
        let template = notifications[which].template

        editTemplate(template, (_template) => {
          notifications[which].template = _template
          onChange(notifications)
        })

      })

    }

    const mount = () => {
      $el.html(render())
      onMount()
    }

    mount()
  }

  const Locations = {
    address: {
      name: __('In-person meeting'),
      edit: () => {
        // language=HTML
        return `
            <div class="display-flex column gap-10">
                <label>${ __('Edit Address') }</label>
                ${ textarea({
                    id: 'location-address',
                    value: getCalendar().meta.location_address,
                }) }
            </div>`
      },
      onMount: () => {
        $('#location-address').on('input change', e => {
          updateMeta({
            location_address: e.target.value,
          })
        })
      },
    },
    google_meet: {
      name: __('Google Meet'),
      edit: () => {
        // language=HTML
        return `
            <button id="edit-google" class="gh-button secondary">${ __('Edit Google Integration') }</button>`
      },
      onMount: () => {
        $('#edit-google').on('click', () => {
          Page.setPage('/google/')
        })
      },
    },
    zoom: {
      name: __('Zoom Meeting'),
      edit: () => {
        // language=HTML
        return `
            <button id="edit-zoom" class="gh-button secondary">${ __('Edit Zoom Integration') }</button>`
      },
      onMount: () => {

        const $zoomButton = $('#edit-zoom')

        if (!GroundhoggCalendar.connections.owners[getCalendar().data.user_id].zoom) {
          $zoomButton.html(__('Connect a Zoom Account'))
        }

        $zoomButton.on('click', () => {
          Page.setPage('/zoom/')
        })
      },
    },
    call_them: {
      name: __('You\'ll call them'),
      edit: () => '',
      onMount: () => {},
    },
    call_you: {
      name: __('They call you'),
      edit: () => {
        // language=HTML
        return `
            <div class="display-flex column gap-10">
                <label>${ __('Number to call') }</label>
                ${ input({
                    type: 'tel',
                    id: 'location-phone',
                    value: getCalendar().meta.location_phone,
                }) }
            </div>`
      },
      onMount: () => {
        $('#location-phone').on('input change', e => {
          updateMeta({
            location_phone: e.target.value,
          })
        })
      },
    },
    custom: {
      name: __('Custom'),
      edit: () => {
        // language=HTML
        return `
            <div class="display-flex column gap-10">
                <label>${ __('Lcoation Details') }</label>
                ${ textarea({
                    id: 'location-custom',
                    value: getCalendar().meta.location_custom,
                }) }
            </div>`
      },
      onMount: () => {
        $('#location-custom').on('input change', e => {
          updateMeta({
            location_custom: e.target.value,
          })
        })
      },
    },
  }

  const pages = [
    {
      slug: /settings/,
      render: () => {

        // language=HTML
        return `
            <div class="gh-header is-sticky">
                <h1>${ __('Edit Calendar') }</h1>
                <div class="display-flex gap-10">
                    <button id="save-settings" class="gh-button primary">${ __('Save Changes') }</button>
                    <button id="more-options" class="gh-button secondary text icon">${ icons.verticalDots }</button>
                </div>
            </div>
            <div class="display-flex gap-20 ${ isSMSActive() ? '' : 'hide-sms' }" id="page">
                <div id="settings" class="display-flex column gap-20">
                    <div class="gh-panel">
                        <div class="gh-panel-header">
                            <h2>${ __('General Settings') }</h2>
                        </div>
                        <div class="inside">
                            <div class="display-flex column gap-10">
                                <div class="gh-rows-and-columns">
                                    <div class="gh-row">
                                        <div class="gh-col">
                                            <label>${ __('Calendar Name') }</label>
                                            ${ input({
                                                id: 'name',
                                                name: 'name',
                                                value: getCalendar().data.name,
                                            }) }
                                        </div>
                                        <div class="gh-col">
                                            <label>${ __('Pretty URL') }</label>
                                            ${ input({
                                                id: 'slug',
                                                name: 'slug',
                                                value: getCalendar().data.slug,
                                            }) }
                                        </div>
                                    </div>
                                    <div class="gh-row">
                                        <div class="gh-col">
                                            <label>${ __('Assign new appointments to...') }</label>
                                            <div>
                                                ${ select({
                                                    id: 'assign-to',
                                                    name: 'user_id',
                                                    selected: getCalendar().data.user_id,
                                                    options: Groundhogg.filters.owners.map(
                                                            user => ( {
                                                                value: user.ID,
                                                                text: `${ user.data.display_name } (${ user.data.user_email })`,
                                                            } )),
                                                }) }
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <hr/>
                                <labe>${ __('Location') }</labe>
                                ${ select({
                                    id: 'location-type',
                                    options: Object.keys(Locations).map(l => ( { value: l, text: Locations[l].name } )),
                                    selected: getCalendar().meta.location_type,
                                }) }
                                <div id="location-settings"></div>
                                <hr/>
                                <label>${ __('Details and/or instructions...') }</label>
                                ${ textarea({
                                    id: 'details',
                                    value: getCalendar().data.description,
                                }) }
                            </div>
                        </div>
                    </div>
                    <div class="gh-panel">
                        <div class="gh-panel-header">
                            <h2>${ __('Availability') }</h2>
                        </div>
                        <div class="inside">
                            <div class="display-flex gap-10 column">
                                <label>${ __('Appointment duration') }</label>
                                <div class="display-flex align-center gap-10">
                                    <div class="gh-input-group">
                                        ${ select({
                                            id: 'duration-hour',
                                            name: 'slot_hour',
                                            options: {
                                                0: '-',
                                                1: __('1 hr', 'groundhogg'),
                                                2: __('2 hrs', 'groundhogg'),
                                                3: __('3 hrs', 'groundhogg'),
                                                4: __('4 hrs', 'groundhogg'),
                                                5: __('5 hrs', 'groundhogg'),
                                                6: __('6 hrs', 'groundhogg'),
                                                7: __('7 hrs', 'groundhogg'),
                                                8: __('8 hrs', 'groundhogg'),
                                                9: __('9 hrs', 'groundhogg'),
                                                10: __('10 hrs', 'groundhogg'),
                                                11: __('11 hrs', 'groundhogg'),
                                                12: __('12 hrs', 'groundhogg'),
                                            },
                                            selected: parseInt(getCalendar().meta.slot_hour),
                                        }) }
                                        ${ select({
                                            id: 'duration-minute',
                                            name: 'slot_minute',
                                            options: {
                                                0: '-',
                                                5: __('5 min', 'groundhogg'),
                                                10: __('10 min', 'groundhogg'),
                                                15: __('15 min', 'groundhogg'),
                                                20: __('20 min', 'groundhogg'),
                                                25: __('25 min', 'groundhogg'),
                                                30: __('30 min', 'groundhogg'),
                                                35: __('35 min', 'groundhogg'),
                                                40: __('40 min', 'groundhogg'),
                                                45: __('45 min', 'groundhogg'),
                                                50: __('50 min', 'groundhogg'),
                                                55: __('55 min', 'groundhogg'),
                                            },
                                            selected: parseInt(getCalendar().meta.slot_minute),
                                        }) }
                                    </div>
                                </div>
                                <hr/>
                                <label>${ __('Invitees must schedule at least...') }</label>
                                <div class="display-flex align-center gap-10">
                                    <div class="gh-input-group">
                                        ${ input({
                                            className: 'small-number',
                                            type: 'number',
                                            id: 'min_booking_period_count',
                                            name: 'min_booking_period_count',
                                            value: getCalendar().meta.min_booking_period_count,
                                        }) }
                                        ${ select({
                                            id: 'min_booking_period_type',
                                            name: 'min_booking_period_type',
                                            selected: getCalendar().meta.min_booking_period_type,
                                            options: {
                                                hours: __('Hours', 'groundhogg'),
                                                days: __('Days', 'groundhogg'),
                                                weeks: __('Weeks', 'groundhogg'),
                                                months: __('Months', 'groundhogg'),
                                            },
                                        }) }
                                    </div>
                                    <span>${ __('in advance.') }</span>
                                </div>
                                <label>${ __('And can schedule...') }</label>
                                <div class="display-flex align-center gap-10">
                                    <div class="gh-input-group">
                                        ${ input({
                                            className: 'small-number',
                                            type: 'number',
                                            id: 'max_booking_period_count',
                                            name: 'max_booking_period_count',
                                            value: getCalendar().meta.max_booking_period_count,
                                        }) }
                                        ${ select({
                                            id: 'max_booking_period_type',
                                            name: 'max_booking_period_type',
                                            selected: getCalendar().meta.max_booking_period_type,
                                            options: {
                                                hours: __('Hours', 'groundhogg'),
                                                days: __('Days', 'groundhogg'),
                                                weeks: __('Weeks', 'groundhogg'),
                                                months: __('Months', 'groundhogg'),
                                            },
                                        }) }
                                    </div>
                                    <span>${ __('into the future.') }</span>
                                </div>
                                <label>${ __('Leave at least...') }</label>
                                <div class="display-flex align-center gap-10">
                                    <div class="gh-input-group">
                                        ${ select({
                                            name: 'buffer_time',
                                            id: 'buffer-time',
                                            selected: getCalendar().meta.buffer_time,
                                            options: {
                                                0: __('No buffer'),
                                                15: __('15 minutes'),
                                                30: __('30 minutes'),
                                                45: __('45 minutes'),
                                                60: __('1 hour'),
                                            },
                                        }) }
                                    </div>
                                    <span>${ __('between appointments.') }</span>
                                </div>
                                <hr/>
                                <label class="display-flex gap-10 align-center">
                                    <span>${ __('Make me look busy') }</span> ${ toggle({
                                    name: 'limit_slots',
                                    id: 'limit-slots',
                                    checked: getCalendar().meta.limit_slots,
                                }) }</label>
                                <div class="display-flex align-center gap-10">
                                    <span>${ __('Show up to') }</span>
                                    ${ input({
                                        id: 'max-slots',
                                        className: 'small-number',
                                        type: 'number',
                                        name: 'busy_slot',
                                        value: getCalendar().meta.busy_slot,
                                        min: 0,
                                    }) }
                                    <span>${ __('available slots per day.') }</span>
                                </div>
                                <div></div>
                                <label class="display-flex gap-10 align-center">
                                    <span>${ __('Limit bookings') }</span> ${ toggle({
                                    name: 'limit_bookings',
                                    id: 'limit-bookings',
                                    checked: getCalendar().meta.limit_bookings,
                                }) }</label>
                                <div class="display-flex align-center gap-10">
                                    <span>${ __('Allow up to') }</span>
                                    ${ input({
                                        id: 'max-bookings',
                                        className: 'small-number',
                                        type: 'number',
                                        name: 'max_bookings',
                                        value: getCalendar().meta.max_bookings,
                                        min: 0,
                                    }) }
                                    <span>${ __('bookings per day.') }</span>
                                </div>
                                <hr/>
                            </div>
                            <p>${ __('Set your availability/working hours') }</p>
                            <div id="business-hours"></div>
                        </div>
                    </div>
                    <div class="gh-panel">
                        <div class="gh-panel-header">
                            <h2>${ __('After Booking') }</h2>
                        </div>
                        <div class="inside">
                            <div class="display-flex column gap-10">
                                <label>${ __('After an appointment is scheduled...') }</label>
                                ${ select({
                                    id: 'after-submit',
                                    selected: getCalendar().meta.after_submit,
                                    options: {
                                        message: __('Show a confirmation message', 'groundhogg'),
                                        redirect: __('Redirect to a landing page', 'groundhogg'),
                                    },
                                }) }
                                <div id="after-submit-settings"
                                     class="display-flex column gap-10 ${ getCalendar().meta.after_submit }">
                                    <div id="redirect" class="display-flex column gap-10">
                                        <label>${ __('Redirect to...') }</label>
                                        ${ input({
                                            id: 'success-page',
                                            className: 'full-width',
                                            value: getCalendar().meta.redirect_link,
                                        }) }
                                    </div>
                                    <div id="message" class="display-flex column gap-10">
                                        <label>${ __('Confirmation message...') }</label>
                                        ${ textarea({
                                            id: 'success-message',
                                            value: getCalendar().meta.message,
                                        }) }
                                    </div>
                                </div>
                                <hr/>
                                <p>${ __('Add additional details/instructions...') }</p>
                                ${ textarea({
                                    id: 'additional-details',
                                    value: getCalendar().meta.additional_notes,
                                }) }
                            </div>
                        </div>
                    </div>
                    <div class="gh-panel">
                        <div class="gh-panel-header">
                            <h2>${ __('Notifications (Sent to the admin)') }</h2>
                        </div>
                        <div class="inside">
                            <p>${ __('When do you want to receive <b>email</b> notifications?') }</p>
                            <div id="admin-notifications"></div>
                            <p>${ __('Add other notifications...') }</p>
                            <div id="custom-notifications"></div>
                            <a id="personalize-custom-notifications">${ __('Personalize Notifications') }</a>
                            <div class="sms-settings">
                                <hr/>
                                <p>${ __('When do you want to receive <b>SMS</b> notifications?') }</p>
                                <div id="admin-sms-notifications"></div>
                                <p>${ __('Add other SMS notifications...') }</p>
                                <div id="custom-sms-notifications"></div>
                                <a id="personalize-custom-sms-notifications">${ __('Personalize Notifications') }</a>
                            </div>
                        </div>
                    </div>
                    <div class="gh-panel">
                        <div class="gh-panel-header">
                            <h2>${ __('Reminders (Sent to the contact)') }</h2>
                        </div>
                        <div class="inside">
                            <p>${ __('When do you want to send <b>email</b> reminders?') }</p>
                            <div id="contact-email-notifications"></div>
                            <p>${ __('Add other email reminders...') }</p>
                            <div id="email-reminders"></div>
                            <a id="personalize-email-reminders">${ __('Personalize Reminders') }</a>
                            <div class="sms-settings">
                                <hr/>
                                <p>${ __('When do you want to send <b>SMS</b> reminders?') }</p>
                                <div id="contact-sms-notifications"></div>
                                <p>${ __('Add other SMS reminders...') }</p>
                                <div id="sms-reminders"></div>
                                <a id="personalize-sms-reminders">${ __('Personalize Reminders') }</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="right">
                    <div class="right-sticky display-flex column gap-20">
                        <div class="gh-panel">
                            <div class="gh-panel-header">
                                <h2>${ __('Embed') }</h2>
                            </div>
                            <div class="inside display-flex column gap-10">
                                <label>${ __('Shortcode') }</label>
                                <div class="gh-input-group">
                                    ${ input({
                                        className: 'code full-width',
                                        readonly: true,
                                        value: `[gh_calendar id="${ getCalendar().ID }" name="${ getCalendar().data.name }"]`,
                                    }) }
                                    <button class="gh-button secondary icon">
                                        ${ icons.duplicate }
                                    </button>
                                </div>
                                <label>${ __('Hosted Link') }</label>
                                <div class="gh-input-group">
                                    ${ input({
                                        className: 'code full-width',
                                        readonly: true,
                                        value: getCalendar().link,
                                    }) }
                                    <button class="gh-button secondary icon">
                                        ${ icons.duplicate }
                                    </button>
                                </div>
                                <div>
                                    <a href="${ getCalendar().link }" target="_blank" class="gh-button secondary">${ __(
                                            'Preview') }</a>
                                </div>
                            </div>
                        </div>
                        <div class="gh-panel">
                            <div class="gh-panel-header">
                                <h2>${ __('Appointments') }</h2>
                            </div>
                            <div class="inside">

                            </div>
                        </div>
                    </div>

                </div>
            </div>
        `
      },
      onMount: (params, setPage) => {

        $('#save-settings').on('click', e => {
          saveChanges().then(() => {
            dialog({
              message: __('Changes saved!'),
            })
          })
        })

        let dataSettingInputs = [
          '#slug',
          '#name',
          '#assign-to',
        ]

        $(dataSettingInputs.join()).on('change', e => {
          updateData({
            [e.target.name]: $(e.target).val(),
          })
        })

        let metaSettingInputs = [
          '#buffer-time',
          '#duration-hour',
          '#duration-minute',
          '#max-bookings',
          '#max-slots',
          '#min_booking_period_count',
          '#min_booking_period_type',
          '#max_booking_period_count',
          '#max_booking_period_type',
        ]

        $(metaSettingInputs.join()).on('change', e => {
          updateMeta({
            [e.target.name]: $(e.target).val(),
          })
        })

        let metaSettingToggles = [
          '#limit-bookings',
          '#limit-slots',
        ]

        $(metaSettingToggles.join()).on('change', e => {
          updateMeta({
            [e.target.name]: e.target.checked,
          })
        })

        let location = getCalendar().meta.location_type

        const $locationSettings = $('#location-settings')
        const $locationType = $('#location-type')

        const mountLocation = () => {
          $locationSettings.html(Locations[location].edit())
          Locations[location].onMount()
        }

        $locationType.on('change', e => {

          location = $locationType.val()

          updateMeta({
            location_type: location,
          })

          mountLocation()
        })

        mountLocation()

        let editors = {
          details: description => updateData({ description }),
          'additional-details': additional_notes => updateMeta({ additional_notes }),
          'success-message': message => updateMeta({ message }),
        }

        Object.keys(editors).forEach(editor => {
          wp.editor.remove(editor)
          tinymceElement(editor, {
            quicktags: false,
            tinymce: {
              height: 200,
            },
          }, editors[editor])
        })

        inputRepeater('#business-hours', {
          rows: getCalendar().meta.rules.map(({ day, start, end }) => ( [day, start, end] )),
          onChange: (rows) => updateMeta({
            rules: rows.map(([day, start, end]) => ( { day, start, end } )),
          }),
          addRow: () => ['monday', '09:00:00', '17:00:00'],
          cells: [
            ({ value, ...props }) => select({
              ...props,
            }, {
              monday: __('Monday'),
              tuesday: __('Tuesday'),
              wednesday: __('Wednesday'),
              thursday: __('Thursday'),
              friday: __('Friday'),
              saturday: __('Saturday'),
              sunday: __('Sunday'),
            }, value),
            props => input({
              type: 'time',
              ...props,
            }),
            props => input({
              type: 'time',
              ...props,
            }),
          ],
        }).mount()

        const reminders = (selector, reminders, onChange) => {

          inputRepeater(selector, {
            rows: reminders.map(({ period, number }) => ( [number, period] )),
            onChange: rows => onChange(rows.map(([number, period]) => ( { number, period } ))),
            addRow: () => [1, 'days'],
            cells: [
              props => input({
                type: 'number',
                className: 'small-number',
                min: 0,
                ...props,
              }),
              ({ value, ...props }) => select({
                ...props,
              }, {
                minutes: __('Minutes before'),
                hours: __('Hours before'),
                days: __('Days before'),
              }, value),
              ({ value, ...props }) => select({
                ...props,
              }, {
                minutes: __('Minute(s) before'),
                hours: __('Hour(s) before'),
                days: __('Day(s) before'),
              }, value),
            ],
          }).mount()

        }

        let {
          notifications = [],
          sms_notifications = [],
          email_reminders = [],
          sms_reminders = [],
        } = getCalendar().meta

        reminders('#custom-notifications', notifications,
          reminders => updateMeta({ notifications: reminders }))
        reminders('#email-reminders', email_reminders,
          reminders => updateMeta({ email_reminders: reminders }))

        if (isSMSActive()) {
          reminders('#custom-sms-notifications', sms_notifications,
            reminders => updateMeta({ sms_notifications: reminders }))

          reminders('#sms-reminders', sms_reminders,
            reminders => updateMeta({ sms_reminders: reminders }))
        }

        let {
          admin_notifications = {},
          admin_sms_notifications = {},
          contact_sms_notifications = {},
          contact_email_notifications = {},
        } = getCalendar().meta

        const editEmailTemplate = (template = {}, saveTemplate = () => {}) => {

          let {
            subject = '',
            content = '',
          } = template

          modal({
            width: 800,
            // language=HTML
            content: `
                <div class="display-flex column gap-10">
                    <label>${ __('Subject line') }</label>
                    ${ inputWithReplacements({
                        id: 'template-subject',
                        className: 'full-width',
                        name: 'subject',
                        value: subject,
                    }) }
                    <label>${ __('Content') }</label>
                    ${ textarea({
                        id: 'template-content',
                        name: 'content',
                        value: content,
                    }) }
                    <div>
                        <button id="save-template" class="gh-button primary">${ __('Save Changes') }</button>
                    </div>
                </div>`,
            onOpen: ({ close }) => {

              $('#template-subject').on('change input', e => {
                subject = e.target.value
              })

              wp.editor.remove('template-content')
              tinymceElement('template-content', {
                quicktags: false,
              }, _content => content = _content)

              $('#save-template').on('click', e => {
                saveTemplate({
                  subject,
                  content,
                })
                close()
              })
            },
          })

        }

        notificationsTable('#admin-notifications', {
          notifications: admin_notifications,
          onChange: admin_notifications => updateMeta({ admin_notifications }),
          editTemplate: editEmailTemplate,
        })

        const editSmsTemplate = (template = {}, saveTemplate = () => {}) => {

          let {
            content = '',
          } = template

          modal({
            width: 500,
            // language=HTML
            content: `
                <div class="display-flex column gap-10">
                    <label>${ __('Edit SMS Content') }</label>
                    ${ textAreaWithReplacements({
                        id: 'template-content',
                        name: 'content',
                        value: content,
                        rows: 5,
                    }) }
                    <div>
                        <button id="save-template" class="gh-button primary">${ __('Save Changes') }</button>
                    </div>
                </div>`,
            onOpen: ({ close }) => {

              $('#template-content').on('change input', e => {
                content = e.target.value
              })

              $('#save-template').on('click', e => {
                saveTemplate({
                  content,
                })
                close()
              })
            },
          })

        }

        notificationsTable('#contact-email-notifications', {
          notifications: contact_email_notifications,
          onChange: contact_email_notifications => updateMeta({ contact_email_notifications }),
          editTemplate: editEmailTemplate,
          forceEnabled: true,
        })

        let {
          personalized_email_notification = {},
          personalized_sms_notification = {},
          personalized_email_reminder = {},
          personalized_sms_reminder = {},
        } = getCalendar().meta

        $('#personalize-custom-notifications').on('click', e => {
          editEmailTemplate(personalized_email_notification, (template) => {
            updateMeta({
              personalized_email_notification: template,
            })
          })
        })

        $('#personalize-email-reminders').on('click', e => {
          editEmailTemplate(personalized_email_reminder, (template) => {
            updateMeta({
              personalized_email_reminder: template,
            })
          })
        })

        if (isSMSActive()) {

          $('#personalize-custom-sms-notifications').on('click', e => {
            editSmsTemplate(personalized_sms_notification, (template) => {
              updateMeta({
                personalized_sms_notification: template,
              })
            })
          })

          $('#personalize-sms-reminders').on('click', e => {
            editSmsTemplate(personalized_sms_reminder, (template) => {
              updateMeta({
                personalized_sms_reminder: template,
              })
            })
          })

          notificationsTable('#contact-sms-notifications', {
            notifications: contact_sms_notifications,
            onChange: contact_sms_notifications => updateMeta({ contact_sms_notifications }),
            editTemplate: editSmsTemplate,
          })

          notificationsTable('#admin-sms-notifications', {
            notifications: admin_sms_notifications,
            onChange: admin_sms_notifications => updateMeta({ admin_sms_notifications }),
            editTemplate: editSmsTemplate,
          })
        }

        $('#after-submit').on('change', e => {

          let value = $(e.target).val()

          updateMeta({
            after_submit: value,
          })

          let $settings = $('#after-submit-settings')

          $settings.removeClass('message')
          $settings.removeClass('redirect')
          $settings.addClass(value)
        })

      },
    },
    {
      slug: /zoom/,
      render: () => {

        // language=HTML
        return `
            <div class="gh-header is-sticky">
                <h2>${ __('Configure Zoom') }</h2>
            </div>
            <div class="display-flex gap-20" id="page">
                <div id="settings" class="display-flex column gap-20">
                    <div>
                        <button id="back-to-settings" class="gh-button secondary text">⬅️
                            ${ __('Back to calendar settings') }
                        </button>
                    </div>
                    <div class="gh-panel">
                        <div class="gh-panel-header">
                            <h2>${ __('Zoom Accounts') }</h2>
                        </div>
                        <div class="inside">
                            <div class="display-flex column gap-10">
                                <p>${ __('Assign a single Zoom account per team member.') }</p>
                                <div>
                                    <table id="zoom-table">

                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `
      },
      onMount: (params, setPage) => {

        const $table = $('#zoom-table')

        $('#back-to-settings').on('click', e => {
          setPage('/settings/')
        })

        const zoomConnection = (owner) => {

          const getZoomAccountId = () => GroundhoggCalendar.connections.owners.hasOwnProperty(owner.ID)
            ? GroundhoggCalendar.connections.owners[owner.ID].zoom
            : false

          // language=HTML
          return `
              <tr>
                  <td>
                      <div><b>${ owner.data.display_name }</b></div>
                      <div>${ owner.data.user_email }</div>
                  </td>
                  <td>
                      ${ getZoomAccountId() ?
                              `<div class="display-flex gap-20 align-center"><span>${ sprintf(__('Connected to %s'),
                                      `<code>${ GroundhoggCalendar.connections.zoom[getZoomAccountId()].email }</code>`) }</span><button class="gh-button danger text small">${ __(
                                      'Disconnect') }</button></div>` :
                              `<a href="${ GroundhoggCalendar.connect.zoom }&user=${ owner.ID }" class="gh-button primary text">${ __(
                                      'Connect a Zoom account') }</a>` }
                  </td>
              </tr>`
        }

        Groundhogg.filters.owners.forEach(owner => {
          $table.append(zoomConnection(owner))
        })
      },
    },
    {
      slug: /google\/[0-9]+/,
      render: () => {

        // language=HTML
        return `
            <div class="gh-header is-sticky">
                <h2>${ __('Edit Google Calendar Connection') }</h2>
            </div>
            <div class="display-flex gap-20" id="page">
                <div id="settings" class="display-flex column gap-20">
                    <div>
                        <button id="back-to-settings" class="gh-button secondary text">⬅️
                            ${ __('Back to Google Connections') }
                        </button>
                    </div>
                </div>
            </div>
        `
      },
      onMount: (params, setPage) => {

        const [slug, ownerId] = params

        $('#back-to-settings').on('click', e => {
          setPage('/google/')
        })

        let changes = {}

        let connection = getGoogleConnection(ownerId)

        const accountPanel = (owner) => {

          // language=HTML
          return `
              <div class="gh-panel">
                  <div class="gh-panel-header">
                      <h2>${ owner.data.display_name }</h2>
                  </div>
                  <div class="inside">
                      <div class="display-flex column gap-10">
                          <div class="display-flex gap-20 align-center">
                              <span>${ sprintf(__('Connected to %s'),
                                      `<code>${ connection.data.account_email }</code>`) }</span>
                          </div>
                          <label>${ __('Add new appointments to...') }</label>
                          ${ select({
                              id: 'add-appointments-to',
                              name: 'add_appointments_to',
                              options: connection.data.all_calendars.map(cal => ( { value: cal.id, text: cal.name } )),
                              selected: connection.data.add_appointments_to,
                          }) }
                          <hr/>
                          <label>${ __('Check these calendars for conflicts') }</label>
                          ${ connection.data.all_calendars.map(cal => {
                              return `<label class="display-flex align-center">${ input({
                                  type: 'checkbox',
                                  name: 'check_for_conflicts[]',
                                  className: 'check-for-conflicts',
                                  checked: connection.data.check_for_conflicts.includes(cal.id),
                                  value: cal.id,
                              }) } <span>${ cal.name }</span></label>`
                          }).join('') }
                          <div style="margin-top: 20px">
                              <button id="save-connection" class="gh-button primary">${ __('Save Changes') }</button>
                          </div>
                      </div>
                  </div>
              </div>`
        }

        $('#settings').append(accountPanel(getOwner(ownerId)))

        $('#add-appointments-to').on('change', e => {
          changes.add_appointments_to = $(e.target).val()
        })

        $('.check-for-conflicts').on('change', e => {
          changes.check_for_conflicts = $('.check-for-conflicts:checked').map((i, el) => {
            return el.value
          }).get()
        })

        $('#save-connection').on('click', e => {
          GoogleConnections.patch(connection.ID, {
            data: {
              ...changes,
            },
          }).then(() => {

            dialog({
              message: __('Connection saved!'),
            })

          })
        })

      },
    },
    {
      slug: /google/,
      render: () => {

        // language=HTML
        return `
            <div class="gh-header is-sticky">
                <h2>${ __('Configure Google Connections') }</h2>
            </div>
            <div class="display-flex gap-20" id="page">
                <div id="settings" class="display-flex column gap-20">
                    <div>
                        <button id="back-to-settings" class="gh-button secondary text">⬅️
                            ${ __('Back to calendar settings') }
                        </button>
                    </div>

                </div>
            </div>
        `
      },
      onMount: (params, setPage) => {

        $('#back-to-settings').on('click', e => {
          setPage('/settings/')
        })

        const accountPanel = (owner) => {

          const connected = () => {

            let connection = getGoogleConnection(owner.ID)

            const getCalName = calId => connection.data.all_calendars.find(cal => cal.id === calId)?.name

            // language=HTML
            return `
                <div class="display-flex column gap-10">
                    <div class="display-flex gap-20 align-center">
                        <span>${ sprintf(__('Connected to %s'),
                                `<code>${ connection.data.account_email }</code>`) }</span>
                    </div>
                    <div>
                        ${ sprintf(__('Add new appointments to %s.'), getCalName(connection.data.add_appointments_to)) }
                    </div>
                    <div>${ __('Check these calendars for conflicts...') }</div>
                    <ul>
                        ${ connection.data.check_for_conflicts.map(cal => {
                            return `<li>${ getCalName(cal) }</li>`
                        }).join('') }
                    </ul>
                    <div class="display-flex">
                        <button class="gh-button primary text edit-connection small" data-id="${ owner.ID }">
                            ${ __('Edit Connection') }
                        </button>
                        <button class="gh-button danger text small">${ __('Disconnect') }</button>
                    </div>
                </div>
            `

          }
          const connect = () => {
            // language=HTML
            return `
                <a href="${ GroundhoggCalendar.connect.google }&user=${ owner.ID }" class="gh-button secondary">${
                        __('Connect a Google Account') }</a>
            `
          }

          const isConnected = () => Boolean(getGoogleConnection(owner.ID))

          // language=HTML
          return `
              <div class="gh-panel">
                  <div class="gh-panel-header">
                      <h2>${ owner.data.display_name }</h2>
                  </div>
                  <div class="inside">
                      ${ isConnected() ? connected() : connect() }
                  </div>
              </div>`
        }

        Groundhogg.filters.owners.forEach(owner => {
          $('#settings').append(accountPanel(owner))
        })

        $('.edit-connection').on('click', e => {
          setPage(`/google/${ e.target.dataset.id }/`)
        })
      },
    },
  ]

  const Page = {

    slug: '',
    currentPage: pages[0],
    params: [],

    getCurSlug () {
      return window.location.hash.substring(1)
    },

    initFromSlug () {
      this.slug = this.getCurSlug()
      this.params = this.getCurSlug().split('/').filter(p => p)
      this.mount()
    },

    init () {

      if (!window.location.hash) {
        history.pushState({}, '', `#/settings/`)
      }

      this.initFromSlug()

      window.addEventListener('popstate', (e) => {
        this.initFromSlug()
      })
    },

    setPage (slug) {
      history.pushState({}, '', `#${ slug }`)
      this.initFromSlug()
    },

    mount () {

      this.currentPage = pages.find(p => this.slug.match(p.slug))

      console.log(this.slug, this.currentPage.slug)

      const setPage = (slug) => this.setPage(slug)

      $('#gh-calendar-settings').html(this.currentPage.render(this.params))
      this.currentPage.onMount(this.params, setPage)

      window.dispatchEvent(new Event('resize'))
    },

  }

  $(() => Page.init())

} )(jQuery)
