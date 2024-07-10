(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.academic-calendar', context).forEach(function (element) {
        // Keep the HTML here so that we can add it all at once, preventing content refreshes.
        let domBuffer = '';

        // Initialize variables to store all events and unique sessions.
        let allEvents = [];
        let uniqueSessions = new Set();

        // Cache objects for frequently used elements.
        const form = context.querySelector('#academic-calendar-filter-form');
        const showPreviousEventsCheckbox = context.querySelector('#show-previous-events');
        const categoryChosen = context.getElementById('edit_category_chosen');
        const calendarContent = context.getElementById('academic-calendar-content');
        const spinner = calendarContent.querySelector('.fa-spinner');

        let groupByMonthCheckbox = null;
        const showGroupByMonth = drupalSettings.academicCalendar.showGroupByMonth;

        // Initial toggle of 'Show previous events' checkbox.
        toggleShowPreviousEvents();

        // Function to toggle visibility of 'Show previous events' checkbox.
        function toggleShowPreviousEvents() {
          const container = showPreviousEventsCheckbox.closest('.js-form-item');
          const shouldShow = showGroupByMonth ? (groupByMonthCheckbox && groupByMonthCheckbox.checked) : (drupalSettings.academicCalendar.groupByMonth === 1);
          container.style.display = shouldShow ? 'block' : 'none';
        }

        // Function to fetch events from the server and display them
        function fetchAndDisplayEvents() {
          calendarContent.innerHTML = '<span class="fa-solid fa-spinner fa-spin"></span>';
          if (spinner) {
            spinner.style.display = 'block';
          }
          // Gather all form data.
          let categories = ['STUDENT'];

          if (categoryChosen) {
            const chosenOptions = categoryChosen.querySelectorAll('.search-choice');
            if (chosenOptions.length > 0) {
              const indices = Array.from(chosenOptions).map(option => parseInt(option.querySelector('a').getAttribute('data-option-array-index')) - 1);
              if (indices.length > 0) {
                const selectElement = document.getElementById('edit-category');
                categories = indices.map(index => selectElement.options[index].value);
              }
            }
          }

          const subsession = form.querySelector('input[name="subsession"]').checked ? '1' : '0';
          const startDate = form.querySelector('.academic-calendar-start-date').value;
          const endDate = form.querySelector('.academic-calendar-end-date').value;
          const session = form.querySelector('select[name="session"]').value;
          const steps = drupalSettings.academicCalendar.steps || 0;

          Drupal.announce('Fetching events.');
          // Make AJAX request to fetch events.
          fetch(`/api/academic-calendar?category=${categories}&subsession=${subsession}&start=${startDate}&end=${endDate}&session=${session}&steps=${steps}`)
            .then(response => response.json())
            .then(events => {
              allEvents = events;
              uniqueSessions.clear();
              events.forEach(event => uniqueSessions.add(event.sessionDisplay));
              populateSessionFilter();
              filterAndDisplayEvents();
            })
            .catch(error => {
              console.error('Error fetching events:', error);
              domBuffer = '<div>Error loading events. Please try again later.</div>';
              Drupal.announce('Error loading events. Please try again later.');
            });
        }

        // Function to filter and display events based on current form state.
        function filterAndDisplayEvents() {
          const searchTerm = form.querySelector('.academic-calendar-search').value.toLowerCase();
          const startDate = new Date(form.querySelector('.academic-calendar-start-date').value);
          const endDate = new Date(form.querySelector('.academic-calendar-end-date').value);
          const selectedSession = form.querySelector('select[name="session"]').value;
          // Filter events based on search term, date range, and selected session.
          const filteredEvents = allEvents.filter(event => {
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
          displayEvents(filteredEvents);

          if (calendarContent) {
            const observer = new MutationObserver(function () {
              if (spinner) {
                spinner.style.display = 'none';
              }
            });
            observer.observe(calendarContent, { childList: true });
          }
        }

        // Function to display filtered events.
        function displayEvents(events) {
          domBuffer = '';

          if (events.length === 0) {
            domBuffer = '<p>No events found matching your criteria.</p>';
            Drupal.announce('No events found matching your criteria.');
            return;
          }

          Drupal.announce(`Displaying ${events.length} events based on filter criteria.`);

          // Sort events chronologically.
          events.sort((a, b) => new Date(a.start) - new Date(b.start));

          const groupByMonth = showGroupByMonth ? (groupByMonthCheckbox && groupByMonthCheckbox.checked) : (drupalSettings.academicCalendar.groupByMonth === 1);
          const showPreviousEvents = showPreviousEventsCheckbox.checked;

          if (groupByMonth) {
            displayGroupedByMonth(events, showPreviousEvents);
          } else {
            displayGroupedBySession(events);
          }

          element.innerHTML = domBuffer;
          domBuffer = '';
        }

        // Function to display events grouped by month.
        function displayGroupedByMonth(events, showPreviousEvents) {
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
            renderMonths(pastMonths, groupedEvents);
          }
          renderMonths(futureMonths, groupedEvents);
        }

        // Function to display events grouped by session.
        function displayGroupedBySession(events) {
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
            domBuffer += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${sessionDisplay}</h2>`;
            events.forEach(event => renderEvent(event, false));
          });
        }

        // Function to render months and their events.
        function renderMonths(months, groupedEvents) {
          months.forEach(month => {
            domBuffer += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${month}</h2>`;
            groupedEvents[month].forEach(event => renderEvent(event, true));
          });
        }

        // Function to render individual event.
        function renderEvent(event, includeSession) {
          domBuffer += event.rendered;
        }

        // Function to populate the session filter dropdown.
        function populateSessionFilter() {
          const sessionSelect = form.querySelector('select[name="session"]');
          const currentValue = sessionSelect.value;


          // Keep the session HTML here so that we can add it all at once, preventing content refreshes.
          let sessionBuffer = '<option value="">All Sessions</option>';

          Array.from(uniqueSessions).sort().forEach(session => {
            sessionBuffer += `<option value="${session}">${session}</option>`;
          });

          sessionSelect.innerHTML = sessionBuffer;
          sessionSelect.value = uniqueSessions.has(currentValue) ? currentValue : '';
        }

        if (showGroupByMonth) {
          groupByMonthCheckbox = context.querySelector('#group-by-month');

          // Set initial state of 'Group by month' checkbox based on Drupal settings.
          if (typeof drupalSettings.academicCalendar.groupByMonth !== 'undefined') {
            groupByMonthCheckbox.checked = drupalSettings.academicCalendar.groupByMonth === 1;
          }

          // Add event listener for changes to 'Group by month' checkbox.
          groupByMonthCheckbox.addEventListener('change', toggleShowPreviousEvents);
        }

        // Initial fetch of events.
        fetchAndDisplayEvents();

        // Attach filter functionality to form elements.
        form.addEventListener('change', function (event) {
          if (['search', 'session', 'start_date', 'end_date', 'subsession', 'group_by_month', 'show_previous_events', 'select', '#subsession', '#group-by-month', '#show-previous-events'].includes(event.target.name)) {
            fetchAndDisplayEvents();
          }
        });

        // Attach search and date filter functionality.
        context.querySelectorAll('.academic-calendar-search, .academic-calendar-start-date, .academic-calendar-end-date').forEach(input => {
          input.addEventListener('input', Drupal.debounce(filterAndDisplayEvents, 300));
          input.addEventListener('change', Drupal.debounce(filterAndDisplayEvents, 300));
        });

        // Handle form submission
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          fetchAndDisplayEvents();
        });

        if (categoryChosen) {
          const observer = new MutationObserver(function () {
            fetchAndDisplayEvents();
          });
          observer.observe(categoryChosen, { childList: true, subtree: true });
        }
      });
    }
  };
})(Drupal, drupalSettings);
