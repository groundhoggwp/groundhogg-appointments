let GroundhoggPicker

( ($) => {

  const { __, sprintf } = wp.i18n

  const icons = {
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
    arrowLeftAlt: `
        <svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 9H3.8l5.6-5.6L8 2l-8 8 8 8 1.4-1.4L3.8 11H20z"/>
        </svg>`,
  }

  const days_of_week = [
    __('Sun', 'groundhogg-calendar'),
    __('Mon', 'groundhogg-calendar'),
    __('Tue', 'groundhogg-calendar'),
    __('Wed', 'groundhogg-calendar'),
    __('Thu', 'groundhogg-calendar'),
    __('Fri', 'groundhogg-calendar'),
    __('Sat', 'groundhogg-calendar'),
  ]

  const months = [
    __('January', 'groundhogg-calendar'),
    __('February', 'groundhogg-calendar'),
    __('March', 'groundhogg-calendar'),
    __('April', 'groundhogg-calendar'),
    __('May', 'groundhogg-calendar'),
    __('June', 'groundhogg-calendar'),
    __('July', 'groundhogg-calendar'),
    __('August', 'groundhogg-calendar'),
    __('September', 'groundhogg-calendar'),
    __('October', 'groundhogg-calendar'),
    __('November', 'groundhogg-calendar'),
    __('December', 'groundhogg-calendar'),
  ]

  const calendar = (el, {
    selected = false,
    locale = 'en-US',
    timeZone = jstz.determine().name(),
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
          return date.toLocaleTimeString(locale, {
            timeZone,
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

  GroundhoggPicker = calendar

})(jQuery)
