(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.academic-calendar', context).forEach(function (element) {
        // Initialize variables to store all events and unique sessions.
        let allEvents = [];
        let uniqueSessions = new Set();

        // Cache objects for frequently used elements.
        const form = context.querySelector('#academic-calendar-filter-form');
        const groupByMonthCheckbox = context.querySelector('#group-by-month');
        const showPreviousEventsCheckbox = context.querySelector('#show-previous-events');

        // Function to toggle visibility of 'Show previous events' checkbox.
        function toggleShowPreviousEvents() {
          const container = showPreviousEventsCheckbox.closest('.js-form-item');
          container.style.display = groupByMonthCheckbox.checked ? 'block' : 'none';
        }

        // Function to fetch events from the server and display them
        function fetchAndDisplayEvents() {
          // Gather all form data.
          // TODO get Chosen selection to load and to work.
          const chosenContainer = form.querySelector('#edit-category');
          const chosenOptions = chosenContainer.querySelectorAll('.search-choice');
          const categories = ['STUDENT'];

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
              element.innerHTML = '<div>Error loading events. Please try again later.</div>';
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

          const calendarContent = document.getElementById('academic-calendar-content');
          if (calendarContent) {
            const observer = new MutationObserver(function () {
              const spinner = calendarContent.querySelector('.fa-spinner');
              if (spinner) {
                spinner.style.display = 'none';
              }
            });
            observer.observe(calendarContent, { childList: true });
          }
        }

        // Function to display filtered events.
        function displayEvents(events) {
          element.innerHTML = '';

          if (events.length === 0) {
            element.innerHTML = '<p>No events found matching your criteria.</p>';
            Drupal.announce('No events found matching your criteria.');
            return;
          }

          Drupal.announce(`Displaying ${events.length} events based on filter criteria.`);

          // Sort events chronologically.
          events.sort((a, b) => new Date(a.start) - new Date(b.start));

          const groupByMonth = groupByMonthCheckbox.checked;
          const showPreviousEvents = showPreviousEventsCheckbox.checked;

          if (groupByMonth) {
            displayGroupedByMonth(events, showPreviousEvents);
          } else {
            displayGroupedBySession(events);
          }
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
          const currentMonth = now.toLocaleString('default', { month: 'long', year: 'numeric' });
          const sortedMonths = Object.keys(groupedEvents).sort((a, b) => new Date(a) - new Date(b));
          const currentMonthIndex = sortedMonths.indexOf(currentMonth);

          if (currentMonthIndex !== -1) {
            const pastMonths = sortedMonths.slice(0, currentMonthIndex);
            const futureMonths = sortedMonths.slice(currentMonthIndex);

            if (showPreviousEvents) {
              Drupal.announce(`Including past events.`);
              renderMonths(pastMonths, groupedEvents);
            }
            renderMonths(futureMonths, groupedEvents);
          } else {
            renderMonths(sortedMonths, groupedEvents);
          }
        }

        // Function to display events grouped by session.
        function displayGroupedBySession(events) {
          Drupal.announce(`Grouping events by session.`);
          const groupedEvents = events.reduce((groups, event) => {
            const group = groups[event.sessionDisplay] || [];
            group.push(event);
            groups[event.sessionDisplay] = group;
            return groups;
          }, {});

          Object.entries(groupedEvents).forEach(([sessionDisplay, events]) => {
            element.innerHTML += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${sessionDisplay}</h2>`;
            events.forEach(event => renderEvent(event, false));
          });
        }

        // Function to render months and their events.
        function renderMonths(months, groupedEvents) {
          months.forEach(month => {
            element.innerHTML += `<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${month}</h2>`;
            groupedEvents[month].forEach(event => renderEvent(event, true));
          });
        }

        // Function to render individual event.
        function renderEvent(event, includeSession) {
          element.innerHTML += event.rendered;
        }

        // Function to populate the session filter dropdown.
        function populateSessionFilter() {
          const sessionSelect = form.querySelector('select[name="session"]');
          const currentValue = sessionSelect.value;

          sessionSelect.innerHTML = '<option value="">All Sessions</option>';

          Array.from(uniqueSessions).sort().forEach(session => {
            sessionSelect.innerHTML += `<option value="${session}">${session}</option>`;
          });

          sessionSelect.value = uniqueSessions.has(currentValue) ? currentValue : '';
        }

        // Set initial state of 'Group by month' checkbox based on Drupal settings.
        if (typeof drupalSettings.academicCalendar !== 'undefined' &&
          typeof drupalSettings.academicCalendar.groupByMonth !== 'undefined') {
          groupByMonthCheckbox.checked = drupalSettings.academicCalendar.groupByMonth === 1;
        }

        // Initial toggle of 'Show previous events' checkbox.
        toggleShowPreviousEvents();

        // Add event listener for changes to 'Group by month' checkbox.
        groupByMonthCheckbox.addEventListener('change', toggleShowPreviousEvents);

        // Initial fetch of events.
        fetchAndDisplayEvents();

        // Attach filter functionality to form elements.
        form.addEventListener('change', function (event) {
          if (['search', 'session', 'start_date', 'end_date', 'subsession,', 'group_by_month', 'show_previous_events', 'select', 'input[name="subsession"]', '#group-by-month', '#show-previous-events'].includes(event.target.name)) {
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
      });
    }
  };
})(Drupal, drupalSettings);
