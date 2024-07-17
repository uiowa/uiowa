(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.sitenow-academic-calendar', context).forEach(function (calendarEl) {
        console.log('element', calendarEl);

        const calendarSettings = drupalSettings.academicCalendar;
        const steps = calendarSettings.steps || 0;
        const initGroupByMonth = calendarSettings.groupByMonth;
        const showGroupByMonth = calendarSettings.showGroupByMonth;
        const formEl = calendarEl.querySelector('#academic-calendar-filter-form');
        const startDateEl = formEl.querySelector('.academic-calendar-start-date');
        const endDateEl = formEl.querySelector('.academic-calendar-end-date');
        const sessionSelectEl = formEl.querySelector('select[name="session"]');
        const categoryEl = formEl.querySelector('#edit_category_chosen');

        // Initialize the AcademicCalendar object.
        const academicCalendar = new AcademicCalendar(
          calendarEl,
          startDateEl.value,
          endDateEl.value,
          steps,
          initGroupByMonth,
          sessionSelectEl.value,
          function() {
            return formEl.querySelector('.academic-calendar-search').value.toLowerCase();
          }
        );

        // Fetch events and populate session filter.
        academicCalendar.fetchEvents().then(() => {
          populateSessionFilter(academicCalendar.uniqueSessions);
        });

        const showPreviousEventsCheckbox = calendarEl.querySelector('#show-previous-events');
        showPreviousEventsCheckbox.addEventListener('change', () => {
          academicCalendar.showPreviousEvents = showPreviousEventsCheckbox.checked;
          academicCalendar.filterAndDisplayEvents(academicCalendar);
        });

        if (calendarSettings.showGroupByMonth) {
          const groupByMonthCheckbox = calendarEl.querySelector('#group-by-month');

          // Set initial state of 'Group by month' checkbox based on Drupal settings.
          if (typeof initGroupByMonth !== 'undefined') {
            groupByMonthCheckbox.checked = initGroupByMonth === 1;
          }

          // Add event listener for changes to 'Group by month' checkbox.
          groupByMonthCheckbox.addEventListener('change', () => {
            academicCalendar.groupByMonth = groupByMonthCheckbox.checked;
            academicCalendar.filterAndDisplayEvents(academicCalendar);
          });

          // Initial toggle of 'Show previous events' checkbox.
          toggleShowPreviousEvents(groupByMonthCheckbox);
        }

        // Attach filter functionality to form elements.
        formEl.addEventListener('change', function (event) {
          if (['search', 'session', 'start_date', 'end_date', 'subsession', 'group_by_month', 'show_previous_events', 'select', '#subsession', '#group-by-month', '#show-previous-events'].includes(event.target.name)) {
            updateAcademicCalendarFromFilters();
            academicCalendar.filterAndDisplayEvents(academicCalendar);
          }
        });

        // Attach search and date filter functionality.
        context.querySelectorAll('.academic-calendar-search, .academic-calendar-start-date, .academic-calendar-end-date').forEach(input => {
          input.addEventListener('input',
            Drupal.debounce(
              function(){academicCalendar.filterAndDisplayEvents(academicCalendar)},
              300
            )
          );
          input.addEventListener('change',
            Drupal.debounce(
              function(){academicCalendar.filterAndDisplayEvents(academicCalendar)},
              300
            )
          );
        });

        // Handle form submission
        formEl.addEventListener('submit', function (e) {
          e.preventDefault();
          updateAcademicCalendarFromFilters();
          academicCalendar.filterAndDisplayEvents(academicCalendar);
        });

        if (categoryEl) {
          const observer = new MutationObserver(function () {
            updateAcademicCalendarFromFilters();
            academicCalendar.filterAndDisplayEvents(academicCalendar);
          });
          observer.observe(categoryEl, { childList: true, subtree: true });
        }

        // Function to update AcademicCalendar object based on form filters.
        function updateAcademicCalendarFromFilters() {
          academicCalendar.startDate = startDateEl.value;
          academicCalendar.endDate = endDateEl.value;
        }

        // Function to populate the session filter dropdown.
        function populateSessionFilter(sessions) {
          const currentValue = sessionSelectEl.value;

          // Keep the session HTML here so that we can add it all at once, preventing content refreshes.
          let sessionBuffer = '<option value="">All Sessions</option>';

          Array.from(sessions).sort().forEach(session => {
            sessionBuffer += `<option value="${session}">${session}</option>`;
          });

          sessionSelectEl.innerHTML = sessionBuffer;
          sessionSelectEl.value = sessions.has(currentValue) ? currentValue : '';
        }

        // Function to toggle visibility of 'Show previous events' checkbox.
        function toggleShowPreviousEvents(checkbox) {
          const container = showPreviousEventsCheckbox.closest('.js-form-item');
          const shouldShow = showGroupByMonth ? (checkbox && checkbox.checked) : (calendarSettings.groupByMonth === 1);
          container.style.display = shouldShow ? 'block' : 'none';
        }
      });
    }
  };

  class AcademicCalendar {
    steps = 0;
    groupByMonth = false;
    showPreviousEvents = false;
    constructor(calendarEl, startDate, endDate, steps, groupByMonth, selectedSession, getSearchValFn = null) {
      // Keep the HTML here so that we can add it all at once, preventing content refreshes.
      this.output = '';
      this.allEvents = [];
      this.calendarEl = calendarEl;
      this.startDate = startDate;
      this.endDate = endDate;
      this.searchTerm = '';
      this.steps = steps;
      this.groupByMonth = groupByMonth;
      this.selectedSession = selectedSession;
      // Initialize variables to store all events and unique sessions.
      this.uniqueSessions = new Set();
      //------------------------------//
      // Cache objects for frequently used elements.
      this.form = calendarEl.querySelector('#academic-calendar-filter-form');
      this.calendarContent = calendarEl.querySelector('#academic-calendar-content');
      this.spinner = this.calendarContent.querySelector('.fa-spinner');

      this.svf = getSearchValFn;
      // console.log('___custom function in constructor___');
      // console.log(this.svf !== null ? this.svf() : '');
    }

    get groupByMonth() {
      return this.groupByMonth;
    }

    set groupByMonth(value) {
      this.groupByMonth = value;
    }

    get getAllEvents() {
      return this.allEvents;
    }

    setAllEvents(value) {
      this.allEvents = value;
    }

    // Function to fetch events from the server and display them.
    async fetchEvents() {
      this.toggleSpinner();

      Drupal.announce('Fetching events.');
      // Make AJAX request to fetch events.
      fetch(`/api/academic-calendar?subsession=1&start=${this.startDate}&end=${this.endDate}&steps=${this.steps}`)
        .then(response => response.json())
        .then(events => {
          this.setAllEvents(events);
          this.uniqueSessions.clear();
          events.forEach(event => this.uniqueSessions.add(event.sessionDisplay));
          this.setAllEvents(events);
          this.filterAndDisplayEvents();
        })
        .catch(error => {
          console.error('Error fetching events:', error);
          this.output = '<div>Error loading events. Please try again later.</div>';
          Drupal.announce('Error loading events. Please try again later.');
        });
    }

    // Function to toggle visibility of spinner.
    toggleSpinner() {
      if (this.spinner) {
        if (this.spinner.style.display === 'block') {
          this.spinner.style.display = 'none';
        } else {
          this.spinner.style.display = 'block';
        }
      }
    }

    // Function to filter and display events based on current form state.
    filterAndDisplayEvents(_context = null) {
      const context = _context === null ? this : _context;
      const searchTerm = context.form.querySelector('.academic-calendar-search').value.toLowerCase();
      const startDate = new Date(context.startDate);
      const endDate = new Date(context.endDate);
      // Filter events based on search term, date range, and selected session.
      const filteredEvents = context.allEvents.filter(event => {
        const eventStart = new Date(event.start);
        const eventEnd = new Date(event.end);
        const matchesSearch = !searchTerm ||
          event.title.toLowerCase().includes(searchTerm) ||
          event.description.toLowerCase().includes(searchTerm);
        const matchesDateRange = (!startDate.valueOf() || eventEnd >= startDate) &&
          (!endDate.valueOf() || eventStart <= endDate);
        const matchesSession = !context.selectedSession || event.sessionDisplay === context.selectedSession;

        return matchesSearch && matchesDateRange && matchesSession;
      });
      // @todo Implement this.
      context.displayEvents(filteredEvents);

      if (context.calendarContent) {
        const observer = new MutationObserver(() => {
          context.toggleSpinner();
        });
        observer.observe(context.calendarContent, { childList: true });
      }
    }

    // Function to display filtered events.
    displayEvents(events) {
      this.output = '';

      if (events.length === 0) {
        this.output = '<p>No events found matching your criteria.</p>';
        Drupal.announce('No events found matching your criteria.');
        return;
      }

      Drupal.announce(`Displaying ${events.length} events based on filter criteria.`);

      // Sort events chronologically.
      events.sort((a, b) => new Date(a.start) - new Date(b.start));

      if (this.groupByMonth) {
        this.displayGroupedByMonth(events, this.showPreviousEvents);
      } else {
        this.displayGroupedBySession(events);
      }

      this.calendarContent.innerHTML = this.output;
      this.output = '';
    }

    // Function to display events grouped by month.
    displayGroupedByMonth(events, showPreviousEvents) {
      const groupedEvents = events.reduce((groups, event) => {
        const date = new Date(event.start);
        const month = date.toLocaleString('default', { month: 'long', year: 'numeric' });
        if (!groups[month]) groups[month] = [];
        groups[month].push(event);
        return groups;
      }, {});

      Drupal.announce(`Grouping events by month.`);
      const now = new Date();
      const currentMonthYear = now.toLocaleString('default', { month: 'long', year: 'numeric' });
      const currentMonthDate = new Date(currentMonthYear);
      const sortedMonths = Object.keys(groupedEvents).sort((a, b) => new Date(a) - new Date(b));
      const splitMonths = (months, current) => {
        const pastMonths = [];
        const futureMonths = [];

        for (let month of months) {
          const monthDate = new Date(month);
          if (monthDate < current) {
            pastMonths.push(month);
          } else {
            futureMonths.push(month);
          }
        }

        return { pastMonths, futureMonths };
      };

      const { pastMonths, futureMonths } = splitMonths(sortedMonths, currentMonthDate);

      if (showPreviousEvents) {
        Drupal.announce(`Including past events.`);
        this.renderMonths(pastMonths, groupedEvents);
      }
      this.renderMonths(futureMonths, groupedEvents);
    }

    // Function to display events grouped by session.
    displayGroupedBySession(events) {
      Drupal.announce(`Grouping events by session.`);
      // Group events by id.
      const groupedEvents = events.reduce((groups, event) => {
        const group = groups[event.sessionId] || { sessionDisplay: event.sessionDisplay, events: [] };
        group.events.push(event);
        groups[event.sessionId] = group;
        return groups;
      }, {});

      // Sort session ids
      const sortedSessionIds = Object.keys(groupedEvents).sort((a, b) => a - b);

      // Display the grouped and sorted events
      sortedSessionIds.forEach(sessionId => {
        const { sessionDisplay, events } = groupedEvents[sessionId];
        this.output += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${sessionDisplay}</h2>`;
        events.forEach(event => this.renderEvent(event, false));
      });
    }

    // Function to render months and their events.
    renderMonths(months, groupedEvents) {
      months.forEach(month => {
        this.output += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${month}</h2>`;
        groupedEvents[month].forEach(event => this.renderEvent(event, true));
      });
    }

    // Function to render individual event.
    renderEvent(event, includeSession) {
      this.output += event.rendered;
    }
  }
})(Drupal, drupalSettings);
