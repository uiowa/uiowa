(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.sitenow-academic-calendar', context).forEach(function (calendarEl) {
        console.log('element', calendarEl);

        const steps = drupalSettings.academicCalendar.steps || 0;
        const initGroupByMonth = drupalSettings.academicCalendar.groupByMonth;

        // Initialize the AcademicCalendar object.
        const academicCalendar = new AcademicCalendar(calendarEl, steps, initGroupByMonth);

        if (drupalSettings.academicCalendar.showGroupByMonth) {
          const groupByMonthCheckbox = calendarEl.querySelector('#group-by-month');

          // Set initial state of 'Group by month' checkbox based on Drupal settings.
          if (typeof initGroupByMonth !== 'undefined') {
            groupByMonthCheckbox.checked = initGroupByMonth === 1;
          }

          // Add event listener for changes to 'Group by month' checkbox.
          groupByMonthCheckbox.addEventListener('change', () => {
            academicCalendar.groupByMonth = groupByMonthCheckbox.checked;
            academicCalendar.filterAndDisplayEvents();
          });
        }

        const showPreviousEventsCheckbox = calendarEl.querySelector('#show-previous-events');

        showPreviousEventsCheckbox.addEventListener('change', () => {
          academicCalendar.showPreviousEvents = showPreviousEventsCheckbox.checked;
          academicCalendar.filterAndDisplayEvents();
        });

        // Attach filter functionality to form elements.
        // form.addEventListener('change', function (event) {
        //   if (['search', 'session', 'start_date', 'end_date', 'subsession', 'group_by_month', 'show_previous_events', 'select', '#subsession', '#group-by-month', '#show-previous-events'].includes(event.target.name)) {
        //     academicCalendar.filterAndDisplayEvents();
        //   }
        // });

        // Attach search and date filter functionality.
        context.querySelectorAll('.academic-calendar-search, .academic-calendar-start-date, .academic-calendar-end-date').forEach(input => {
          input.addEventListener('input', Drupal.debounce(academicCalendar.filterAndDisplayEvents, 300));
          input.addEventListener('change', Drupal.debounce(academicCalendar.filterAndDisplayEvents, 300));
        });

        // Handle form submission
        // form.addEventListener('submit', function (e) {
        //   e.preventDefault();
        //   academicCalendar.filterAndDisplayEvents();
        // });

        // if (categoryChosen) {
        //   const observer = new MutationObserver(function () {
        //     academicCalendar.filterAndDisplayEvents();
        //   });
        //   observer.observe(categoryChosen, { childList: true, subtree: true });
        // }
      });
    }
  };

  class AcademicCalendar {
    steps = 0;
    groupByMonth = false;
    showPreviousEvents = false;
    constructor(calendarEl, steps, groupByMonth, showPreviousEvents) {
      // Keep the HTML here so that we can add it all at once, preventing content refreshes.
      this.domBuffer = '';
      this.allEvents = [];
      this.calendarEl = calendarEl;
      this.steps = steps;
      this.groupByMonth = groupByMonth;
      this.showPreviousEvents = showPreviousEvents;
      // Initialize variables to store all events and unique sessions.
      this.uniqueSessions = new Set();
      // Cache objects for frequently used elements.
      this.form = calendarEl.querySelector('#academic-calendar-filter-form');
      this.showPreviousEventsCheckbox = calendarEl.querySelector('#show-previous-events');
      console.log("calendarEl", calendarEl);
      this.categoryChosen = calendarEl.querySelector('#edit_category_chosen');
      this.calendarContent = calendarEl.querySelector('#academic-calendar-content');
      this.spinner = this.calendarContent.querySelector('.fa-spinner');
      this.categories = ['STUDENT'];
      this.startDate = this.form.querySelector('.academic-calendar-start-date').value;
      this.endDate = this.form.querySelector('.academic-calendar-end-date').value;
      this.sessionSelectEl = this.form.querySelector('select[name="session"]');
      this.selectedSession = this.form.querySelector('select[name="session"]').value;

      // Initial toggle of 'Show previous events' checkbox.
      // this.toggleShowPreviousEvents();
      this.fetchEvents();
    }

    get groupByMonth() {
      return this.groupByMonth;
    }

    set groupByMonth(value) {
      this.groupByMonth = value;
    }

    // Function to fetch events from the server and display them.
    fetchEvents() {
      this.toggleSpinner();
      this.filterCategories();

      Drupal.announce('Fetching events.');
      // Make AJAX request to fetch events.
      fetch(`/api/academic-calendar?subsession=1&start=${this.startDate}&end=${this.endDate}&steps=${this.steps}`)
        .then(response => response.json())
        .then(events => {
          this.allEvents = events;
          this.uniqueSessions.clear();
          events.forEach(event => this.uniqueSessions.add(event.sessionDisplay));
          this.populateSessionFilter();
          this.filterAndDisplayEvents();
        })
        .catch(error => {
          console.error('Error fetching events:', error);
          this.domBuffer = '<div>Error loading events. Please try again later.</div>';
          Drupal.announce('Error loading events. Please try again later.');
        });
    }

    // Function to toggle visibility of 'Show previous events' checkbox.
    toggleShowPreviousEvents() {
      const container = this.showPreviousEventsCheckbox.closest('.js-form-item');
      const shouldShow = this.showGroupByMonth ? (this.groupByMonthCheckbox && this.groupByMonthCheckbox.checked) : (drupalSettings.academicCalendar.groupByMonth === 1);
      container.style.display = shouldShow ? 'block' : 'none';
    }

    // Function to filter categories.
    filterCategories() {
      if (this.categoryChosen) {
        const chosenOptions = this.categoryChosen.querySelectorAll('.search-choice');
        if (chosenOptions.length > 0) {
          const indices = Array.from(chosenOptions).map(option => parseInt(option.querySelector('a').getAttribute('data-option-array-index')) - 1);
          if (indices.length > 0) {
            const selectElement = document.getElementById('edit-category');
            this.categories = indices.map(index => selectElement.options[index].value);
          }
        }
      }
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

    // Function to populate the session filter dropdown.
    populateSessionFilter() {
      const currentValue = this.sessionSelectEl.value;

      // Keep the session HTML here so that we can add it all at once, preventing content refreshes.
      let sessionBuffer = '<option value="">All Sessions</option>';

      Array.from(this.uniqueSessions).sort().forEach(session => {
        sessionBuffer += `<option value="${session}">${session}</option>`;
      });

      this.sessionSelectEl.innerHTML = sessionBuffer;
      this.sessionSelectEl.value = this.uniqueSessions.has(currentValue) ? currentValue : '';
    }

    // Function to filter and display events based on current form state.
    filterAndDisplayEvents() {
      const searchTerm = this.form.querySelector('.academic-calendar-search').value.toLowerCase();
      const startDate = new Date(this.startDate);
      const endDate = new Date(this.endDate);
      const selectedSession = this.sessionSelectEl.value;
      // Filter events based on search term, date range, and selected session.
      const filteredEvents = this.allEvents.filter(event => {
        const eventStart = new Date(event.start);
        const eventEnd = new Date(event.end);
        const matchesSearch = !searchTerm ||
          event.title.toLowerCase().includes(searchTerm) ||
          event.description.toLowerCase().includes(searchTerm);
        const matchesDateRange = (!startDate.valueOf() || eventEnd >= startDate) &&
          (!endDate.valueOf() || eventStart <= endDate);
        const matchesSession = !selectedSession || event.sessionDisplay === selectedSession;

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
      this.domBuffer = '';

      if (events.length === 0) {
        this.domBuffer = '<p>No events found matching your criteria.</p>';
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

      this.calendarContent.innerHTML = this.domBuffer;
      this.domBuffer = '';
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
        this.domBuffer += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${sessionDisplay}</h2>`;
        events.forEach(event => renderEvent(event, false));
      });
    }

    // Function to render months and their events.
    renderMonths(months, groupedEvents) {
      months.forEach(month => {
        this.domBuffer += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${month}</h2>`;
        groupedEvents[month].forEach(event => this.renderEvent(event, true));
      });
    }

    // Function to render individual event.
    renderEvent(event, includeSession) {
      this.domBuffer += event.rendered;
    }
  }
})(Drupal, drupalSettings);
