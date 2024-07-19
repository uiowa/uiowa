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
          getSearchTerm(),
          startDateEl.value,
          endDateEl.value,
          steps,
          initGroupByMonth,
          sessionSelectEl.value
        );

        // Fetch events and populate session filter.
        academicCalendar.fetchEvents().then(() => {
          populateSessionFilter(academicCalendar.uniqueSessions);
        });

        const showPreviousEventsCheckbox = calendarEl.querySelector('#show-previous-events');
        showPreviousEventsCheckbox.addEventListener('change', () => {
          academicCalendar.showPreviousEvents = showPreviousEventsCheckbox.checked;
          updateFilterDisplay();
        });

        if (showGroupByMonth) {
          const groupByMonthCheckbox = calendarEl.querySelector('#group-by-month');

          // Set initial state of 'Group by month' checkbox based on Drupal settings.
          if (typeof initGroupByMonth !== 'undefined') {
            groupByMonthCheckbox.checked = initGroupByMonth === 1;
          }

          // Add event listener for changes to 'Group by month' checkbox.
          groupByMonthCheckbox.addEventListener('change', () => {
            academicCalendar.groupByMonth = groupByMonthCheckbox.checked;
            updateFilterDisplay();
          });

          // Initial toggle of 'Show previous events' checkbox.
          toggleShowPreviousEvents(groupByMonthCheckbox);
        }

        // Attach filter functionality to form elements.
        formEl.addEventListener('change', function (event) {
          if (['search', 'session', 'start_date', 'end_date', 'subsession', 'group_by_month', 'show_previous_events', 'select', '#subsession', '#group-by-month', '#show-previous-events'].includes(event.target.name)) {
            updateFilterDisplay();
          }
        });

        // Attach search and date filter functionality.
        context.querySelectorAll('.academic-calendar-search, .academic-calendar-start-date, .academic-calendar-end-date').forEach(input => {
          input.addEventListener('input',
            Drupal.debounce(
              () => {
                updateFilterDisplay(true);
              },
              300
            )
          );
          input.addEventListener('change',
            Drupal.debounce(
              () => {
                updateFilterDisplay(true);
              },
              300
            )
          );
        });

        // Handle form submission
        formEl.addEventListener('submit', function (e) {
          e.preventDefault();
          updateFilterDisplay();
        });

        if (categoryEl) {
          const observer = new MutationObserver(function () {
            updateFilterDisplay();
          });
          observer.observe(categoryEl, { childList: true, subtree: true });
        }

        // Wrapper function to call updateAcademicCalendarFromFilters() and then academicCalendar.filterAndDisplayEvents().
        function updateFilterDisplay(call = false) {
          if (call) {
            updateAcademicCalendarFromFilters();
            academicCalendar.filterAndDisplayEvents.call(academicCalendar);
          }
          else{
            updateAcademicCalendarFromFilters();
            academicCalendar.filterAndDisplayEvents();
          }
        }

        // Function to get search term based on form filters.
        function getSearchTerm() {
          return formEl.querySelector('.academic-calendar-search').value.toLowerCase();
        }

        // Function to update AcademicCalendar object based on form filters.
        function updateAcademicCalendarFromFilters() {
          academicCalendar.searchTerm = getSearchTerm();
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
    constructor(calendarEl, searchTerm, startDate, endDate, steps, groupByMonth, selectedSession) {
      // Keep the HTML here so that we can add it all at once, preventing content refreshes.
      this.domOutput = document.createElement("div");
      this.allEvents = [];
      this.calendarEl = calendarEl;
      this.startDate = startDate;
      this.endDate = endDate;
      this.searchTerm = searchTerm;
      this.steps = steps;
      this.groupByMonth = groupByMonth;
      this.selectedSession = selectedSession;
      // Initialize variables to store all events and unique sessions.
      this.uniqueSessions = new Set();
      //------------------------------//
      // Cache objects for frequently used elements.
      this.calendarContent = calendarEl.querySelector('#academic-calendar-content');
      this.spinner = this.calendarContent.querySelector('.fa-spinner');
    }

    get groupByMonth() {
      return this.groupByMonth;
    }

    set groupByMonth(value) {
      this.groupByMonth = value;
    }

    // Function to fetch events from the server and display them.
    async fetchEvents() {
      this.toggleSpinner();

      Drupal.announce('Fetching events.');
      // Make AJAX request to fetch events.
      fetch(`/api/academic-calendar?subsession=1&start=${this.startDate}&end=${this.endDate}&steps=${this.steps}`)
        .then(response => response.json())
        .then(events => {
          this.allEvents = events;
          this.uniqueSessions.clear();
          events.forEach((event) => {
            this.uniqueSessions.add(event.sessionDisplay)
            event.domTree = this.parseCardDomString(event.rendered)
          });
          this.allEvents = events;
          this.filterAndDisplayEvents();
          console.log(this.allEvents);
        })
        .catch(error => {
          console.error('Error fetching events:', error);

          this.domOutput = document.createElement("div");
          const eventsError = document.createElement("div");
          eventsError.innerText = 'Error loading events. Please try again later.';
          this.domOutput.append(eventsError);

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

    // Function to parse HTML strings in to card dom trees.
    parseCardDomString(string) {
      const domTreeEl = new DOMParser().parseFromString(string, "text/html");
      return domTreeEl.querySelector('.card');
    }

    // Function to filter and display events based on current form state.
    filterAndDisplayEvents() {
      const searchTerm = this.searchTerm;
      const startDate = new Date(this.startDate);
      const endDate = new Date(this.endDate);

      // Filter events based on search term, date range, and selected session.
      const filteredEvents = this.allEvents.filter(event => {
        const eventStart = new Date(event.start);
        const eventEnd = new Date(event.end);
        const matchesSearch = !searchTerm ||
          event.title.toLowerCase().includes(searchTerm) ||
          event.description.toLowerCase().includes(searchTerm);
        const matchesDateRange = (!startDate.valueOf() || eventEnd >= startDate) &&
          (!endDate.valueOf() || eventStart <= endDate);
        const matchesSession = !this.selectedSession || event.sessionDisplay === this.selectedSession;

        return matchesSearch && matchesDateRange && matchesSession;
      });
      // @todo Implement this.
      this.displayEvents(filteredEvents);

      if (this.calendarContent) {
        const observer = new MutationObserver(() => {
          this.toggleSpinner();
        });
        observer.observe(this.calendarContent, { childList: true });
      }
    }

    // Function to display filtered events.
    displayEvents(events) {
      this.domOutput = document.createElement("div");

      if (events.length === 0) {
        const noEvents = document.createElement("p");
        noEvents.innerText = 'No events found matching your criteria.';
        this.domOutput.append(noEvents);

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

      this.calendarContent.replaceChildren(...this.domOutput.childNodes)
      this.domOutput = document.createElement("div");
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

        const headline = document.createElement("h2");
        let classesToAdd = ['headline', 'headline--serif', 'block-margin__bottom--extra', 'block-padding__top'];
        headline.classList.add(...classesToAdd);
        headline.innerText = sessionDisplay;
        this.domOutput.append(headline);

        events.forEach(event => this.renderEvent(event, false));
      });
    }

    // Function to render months and their events.
    renderMonths(months, groupedEvents) {
      months.forEach(month => {

        const headline = document.createElement("h2");
        let classesToAdd = ['headline', 'headline--serif', 'block-margin__bottom--extra', 'block-padding__top'];
        headline.classList.add(...classesToAdd);
        headline.innerText = month;
        this.domOutput.append(headline);

        groupedEvents[month].forEach(event => this.renderEvent(event, true));
      });
    }

    // Function to render individual event.
    renderEvent(event, includeSession) {
      this.domOutput.append(event.domTree);
    }
  }
})(Drupal, drupalSettings);
