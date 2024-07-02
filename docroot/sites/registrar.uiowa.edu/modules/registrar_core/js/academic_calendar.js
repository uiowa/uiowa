(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.academic-calendar', context).forEach(function (element) {
        // Initialize variables to store all events and unique sessions.
        let allEvents = [];
        let uniqueSessions = new Set();

        // Cache objects for frequently used elements.
        const $form = $('#academic-calendar-filter-form', context);
        const $groupByMonthCheckbox = $('#group-by-month', context);
        const $showPreviousEventsCheckbox = $('#show-previous-events', context);

        // Function to toggle visibility of 'Show previous events' checkbox.
        function toggleShowPreviousEvents() {
          const $container = $showPreviousEventsCheckbox.closest('.js-form-item');
          $container.toggle($groupByMonthCheckbox.is(':checked'));
        }

        // Function to fetch events from the server and display them
        function fetchAndDisplayEvents() {
          // Gather all form data.
          const categories = $form.find('select[name="category[]"]').val() || ['STUDENT'];
          const subsession = $form.find('input[name="subsession"]').is(':checked') ? '1' : '0';
          const startDate = $form.find('.academic-calendar-start-date').val();
          const endDate = $form.find('.academic-calendar-end-date').val();
          const session = $form.find('select[name="session"]').val();
          const steps = drupalSettings.academicCalendar.steps || 0;

          // Make AJAX request to fetch events.
          $.ajax({
            url: '/api/academic-calendar',
            method: 'GET',
            data: {
              category: categories,
              subsession: subsession,
              start: startDate,
              end: endDate,
              session: session,
              steps: steps
            },
            success: function(events) {
              allEvents = events;
              uniqueSessions.clear();
              events.forEach(event => uniqueSessions.add(event.sessionDisplay));
              populateSessionFilter();
              filterAndDisplayEvents();
            },
            error: function(xhr, status, error) {
              console.error('Error fetching events:', error);
              $(element).html('<div>Error loading events. Please try again later.</div>');
            }
          });
        }

        // Function to filter and display events based on current form state.
        function filterAndDisplayEvents() {
          const searchTerm = $('.academic-calendar-search').val().toLowerCase();
          const startDate = new Date($('.academic-calendar-start-date').val());
          const endDate = new Date($('.academic-calendar-end-date').val());
          const selectedSession = $form.find('select[name="session"]').val();

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
        }

        // Function to display filtered events.
        function displayEvents(events) {
          $(element).empty();

          if (events.length === 0) {
            $(element).append('<p>No events found matching your criteria.</p>');
            return;
          }

          // Sort events chronologically.
          events.sort((a, b) => new Date(a.start) - new Date(b.start));

          const groupByMonth = $groupByMonthCheckbox.is(':checked');
          const showPreviousEvents = $showPreviousEventsCheckbox.is(':checked');

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

          const now = new Date();
          const currentMonth = now.toLocaleString('default', { month: 'long', year: 'numeric' });
          const sortedMonths = Object.keys(groupedEvents).sort((a, b) => new Date(a) - new Date(b));
          const currentMonthIndex = sortedMonths.indexOf(currentMonth);

          if (currentMonthIndex !== -1) {
            const pastMonths = sortedMonths.slice(0, currentMonthIndex);
            const futureMonths = sortedMonths.slice(currentMonthIndex);

            if (showPreviousEvents) {
              renderMonths(pastMonths, groupedEvents);
            }
            renderMonths(futureMonths, groupedEvents);
          } else {
            renderMonths(sortedMonths, groupedEvents);
          }
        }

        // Function to display events grouped by session.
        function displayGroupedBySession(events) {
          const groupedEvents = events.reduce((groups, event) => {
            const group = groups[event.sessionDisplay] || [];
            group.push(event);
            groups[event.sessionDisplay] = group;
            return groups;
          }, {});

          Object.entries(groupedEvents).forEach(([sessionDisplay, events]) => {
            $(element).append(`<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${sessionDisplay}</h2>`);
            events.forEach(event => renderEvent(event, false));
          });
        }

        // Function to render months and their events.
        function renderMonths(months, groupedEvents) {
          months.forEach(month => {
            $(element).append(`<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${month}</h2>`);
            groupedEvents[month].forEach(event => renderEvent(event, true));
          });
        }

        // Function to render individual event.
        function renderEvent(event, includeSession) {
          $(element).append(event.rendered);
        }

        // Function to populate the session filter dropdown.
        function populateSessionFilter() {
          const $sessionSelect = $form.find('select[name="session"]');
          const currentValue = $sessionSelect.val();

          $sessionSelect.empty().append('<option value="">All Sessions</option>');

          Array.from(uniqueSessions).sort().forEach(session => {
            $sessionSelect.append(`<option value="${session}">${session}</option>`);
          });

          if (currentValue && uniqueSessions.has(currentValue)) {
            $sessionSelect.val(currentValue);
          } else {
            $sessionSelect.val('');
          }
        }

        // Set initial state of 'Group by month' checkbox based on Drupal settings.
        if (typeof drupalSettings.academicCalendar !== 'undefined' &&
          typeof drupalSettings.academicCalendar.groupByMonth !== 'undefined') {
          $groupByMonthCheckbox.prop('checked', drupalSettings.academicCalendar.groupByMonth === 1);
        }

        // Initial toggle of 'Show previous events' checkbox.
        toggleShowPreviousEvents();

        // Add event listener for changes to 'Group by month' checkbox.
        $groupByMonthCheckbox.on('change', toggleShowPreviousEvents);

        // Initial fetch of events.
        fetchAndDisplayEvents();

        // Attach filter functionality to form elements.
        $form.on('change', 'select, input[name="subsession"], #group-by-month, #show-previous-events', function() {
          fetchAndDisplayEvents();
        });

        // Attach search and date filter functionality.
        $('.academic-calendar-search, .academic-calendar-start-date, .academic-calendar-end-date', context).on('input change', Drupal.debounce(filterAndDisplayEvents, 300));

        // Handle form submission
        $form.on('submit', function(e) {
          e.preventDefault();
          fetchAndDisplayEvents();
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
