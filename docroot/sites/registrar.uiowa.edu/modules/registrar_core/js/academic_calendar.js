(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.sitenow-academic-calendar', context).forEach(function (calendarEl) {
        const calendarSettings = drupalSettings.academicCalendar;
        const steps = calendarSettings.steps || 0;
        const includePastSessions = calendarSettings.includePastSessions || 0;
        const initGroupByMonth = calendarSettings.groupByMonth;
        const showGroupByMonth = calendarSettings.showGroupByMonth;
        const formEl = calendarEl.querySelector('#academic-calendar-filter-form');
        const formEls = getFormEls(formEl);

        // Initialize the AcademicCalendar object.
        const academicCalendar = new AcademicCalendar(
          calendarEl,
          steps,
          includePastSessions,
          initGroupByMonth,
          getFilterValues()
        );

        // Toggle date input visibility if session is selected.
        function toggleDateInputVisibility() {
          const selectedSession = formEls.sessionSelectEl.value;
          if (selectedSession === "") {
            formEls.startDateEl.closest('.js-form-item').style.display = "block";
          } else {
            formEls.startDateEl.closest('.js-form-item').style.display = "none";
          }
        }

        // Fetch events and populate session filter.
        academicCalendar.fetchEvents().then(() => {
          populateSessionFilter(academicCalendar.uniqueSessions);
        });

        formEls.sessionSelectEl.addEventListener('change', function() {
          toggleDateInputVisibility();
          updateFilterDisplay();
        });

        if (showGroupByMonth) {
          const groupByMonthCheckbox = calendarEl.querySelector('#group-by-month');

          // Set initial state of 'Group by month' checkbox based on Drupal settings.
          groupByMonthCheckbox.checked = initGroupByMonth;
          academicCalendar.groupByMonth = initGroupByMonth;
          // Add event listener for changes to 'Group by month' checkbox.
          groupByMonthCheckbox.addEventListener('change', () => {
            academicCalendar.groupByMonth = groupByMonthCheckbox.checked;
            updateFilterDisplay();
          });
        } else {
          academicCalendar.groupByMonth = initGroupByMonth;
        }

        // Attach filter functionality to form elements.
        formEl.addEventListener('change', function (event) {
          if (['search', 'session', 'start_date', 'group_by_month', 'select', '#group-by-month', 'category'].includes(event.target.name)) {
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

        // Form reset.
        formEls.resetButton.style.display = 'none';

        if (formEls.resetButton) {
          formEls.resetButton.addEventListener('click', function (e) {
            e.preventDefault();
            formEl.reset();
            updateFilterDisplay();
            formEls.resetButton.style.display = 'none';
          });
        }

        if (formEls.categoryEl) {
          const observer = new MutationObserver(function () {
            updateFilterDisplay();
          });
          observer.observe(formEls.categoryEl, { childList: true, subtree: true });
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
            if (formEls.resetButton) {
              formEls.resetButton.style.display = 'inline-block';
            }
          }
          toggleDateInputVisibility();
        }

        // Function to update AcademicCalendar object based on form filters.
        function updateAcademicCalendarFromFilters() {
          const filterValues = getFilterValues();
          academicCalendar.searchTerm = filterValues.searchTerm;
          academicCalendar.startDate = filterValues.startDate;
          academicCalendar.selectedSession = filterValues.selectedSession;
          academicCalendar.selectedCategories = filterValues.selectedCategories;
          // Update groupByMonth.
          if (showGroupByMonth) {
            const groupByMonthCheckbox = calendarEl.querySelector('#group-by-month');
            academicCalendar.groupByMonth = groupByMonthCheckbox ? groupByMonthCheckbox.checked : initGroupByMonth;
          }
        }

        // Function to get the values of the filters at call time.
        function getFilterValues() {
          return {
            'searchTerm' : formEls.searchTermEl.value.toLowerCase(),
            'startDate' : formEls.startDateEl.value,
            'selectedSession' : formEls.sessionSelectEl.value,
            'selectedCategories' : formEls.categoryEl.value,
          };
        }

        // Function to get the form elements for future use.
        function getFormEls(formEl) {
          return {
            'searchTermEl' : formEl.querySelector('.academic-calendar-search'),
            'startDateEl' : formEl.querySelector('.academic-calendar-start-date'),
            'sessionSelectEl' : formEl.querySelector('select[name="session"]'),
            'categoryEl' : formEl.querySelector('select[name="category"]'),
            'resetButton' : formEl.querySelector('.js-form-reset'),
          }
        }

        // Function to populate the session filter dropdown.
        function populateSessionFilter(sessions) {
          const currentValue = formEls.sessionSelectEl.value;

          // Keep the session HTML here so that we can add it all at once, preventing content refreshes.
          let sessionBuffer = '<option value="">All Sessions</option>';
          const sessionsArray = Array.from(sessions);
          const sessionsSortArray = [];
          const sessionsMap = {};

          const weightLookup = {
            '' : '00',
            '4 Week' : '02',
            '6 Week I' : '04',
            '6 Week II' : '06',
            '8 Week' : '08',
            '12 Week' : '10',
          };
          const seasonLookup = {
            'spring' : '00',
            'summer' : '02',
            'fall' : '04',
            'winter' : '06',
          };

          sessionsArray.forEach((session) =>{
            const subSessionSplit = session.split(' - ');
            const isSubSession = subSessionSplit.length > 1;
            const subSessionWeight = weightLookup[isSubSession ? subSessionSplit[1] : ''];

            const seasonYear = subSessionSplit[0].split(' ');
            const seasonWeight = seasonLookup[seasonYear[0].toLowerCase()];
            const year = seasonYear[1];
            const sortString = year + '-' + seasonWeight + '-' + subSessionWeight;

            sessionsSortArray.push(sortString);
            sessionsMap[sortString] = session;
          });

          sessionsSortArray.sort().forEach(sessionLookupString => {
            const mappedSession = sessionsMap[sessionLookupString];

            // This just allows us to tell if we have a subsession,
            //     and tab it in for hierarchy in the select list.
            const isSubSession = sessionLookupString.slice(-2) === '00' ? '' : '&emsp;';
            sessionBuffer += `<option value="${mappedSession}">${isSubSession + mappedSession}</option>`;
          });

          formEls.sessionSelectEl.innerHTML = sessionBuffer;
          formEls.sessionSelectEl.value = sessions.has(currentValue) ? currentValue : '';
        }
      });
    }
  };

  class AcademicCalendar {
    steps = 0;
    groupByMonth = false;
    constructor(calendarEl, steps, includePastSessions, groupByMonth, filterValues) {
      // Keep the HTML here so that we can add it all at once, preventing content refreshes.
      this.domOutput = document.createElement("div");
      this.allEvents = [];
      this.startDate = filterValues.startDate;
      // @todo remove endDate once approved by registrar.
      this.endDate = '';
      this.searchTerm = filterValues.searchTerm;
      this.steps = steps;
      this.includePastSessions = includePastSessions;
      this.groupByMonth = groupByMonth;
      this.selectedSession = filterValues.selectedSession;
      this.selectedCategories = filterValues.selectedCategories;
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
      const date = new Date();

      let day = date.getDate();
      let month = date.getMonth() + 1;
      let year = date.getFullYear();

      // This arrangement can be altered based on how we want the date's format to appear.
      let startDate = `${year-2}-${month}-${day}`;
      let endDate = `${year+2}-${month}-${day}`;

      this.toggleSpinner();

      Drupal.announce('Fetching events.');
      // Make AJAX request to fetch events.
      // Use `await` so we don't return a promise before the fetch is done.
      const fetchResults = await fetch(`/api/academic-calendar?subsession=1&start=${this.startDate}&end=${this.endDate}&steps=${this.steps}&includePastSessions=${this.includePastSessions}`)
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

      // Filter events based on search term, date range, and selected session.
      const filteredEvents = this.allEvents.filter(event => {
        const startCheck = this.startCheck(event);

        const matchesSearch = !searchTerm ||
          event.title.toLowerCase().includes(searchTerm) ||
          event.description.toLowerCase().includes(searchTerm);

        const matchesSession = !this.selectedSession || event.sessionDisplay === this.selectedSession;

        let matchesCategories;
        matchesCategories = event.categories && Object.keys(event.categories).includes(this.selectedCategories);

        return matchesSearch && startCheck && matchesSession && matchesCategories;
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

    // Function to check if the event matches the criteria of the start date filter.
    startCheck(event) {
      const today = new Date();
      const startDate = new Date(this.startDate);
      const eventEnd = new Date(event.end);
      let startCheck;
      if (this.selectedSession){
        startCheck = true;
      }
      else if (!startDate.valueOf()) {
        startCheck = eventEnd >= today;
      }
      else {
        startCheck = eventEnd >= startDate;
      }

      if (startCheck && startDate > new Date(event.start)) {
        startCheck = false;
      }

      return startCheck;
    }

    // Function to display filtered events.
    displayEvents(events) {
      this.domOutput = document.createElement("div");

      if (events.length === 0) {
        const noEvents = document.createElement("p");
        noEvents.innerText = 'No events found matching your criteria.';
        this.domOutput.append(noEvents);
        this.calendarContent.replaceChildren(...this.domOutput.childNodes)
        this.domOutput = document.createElement("div");

        Drupal.announce('No events found matching your criteria.');
        return;
      }

      Drupal.announce(`Displaying ${events.length} events based on filter criteria.`);

      // Sort events chronologically.
      events.sort((a, b) => new Date(a.start) - new Date(b.start));

      if (this.groupByMonth) {
        this.displayGroupedByMonth(events);
      } else {
        this.displayGroupedBySession(events);
      }

      // Do any last minute changes here.
      this.addDateHiders(this.domOutput);

      this.calendarContent.replaceChildren(...this.domOutput.childNodes)
      this.domOutput = document.createElement("div");
    }

    addDateHiders(node) {
      const datesFound= [];

      node.childNodes.forEach((node)=>{
        const date = node.querySelector('.media--date');

        if (date) {
          const entryIndex = date.innerText;

          if (datesFound.includes(entryIndex)) {
            date.classList.add('element-invisible');
          }
          else {
            date.classList.remove('element-invisible');
            datesFound.push(entryIndex);
          }
        }
      })
    }

    // Function to display events grouped by month.
    displayGroupedByMonth(events) {
      const groupedEvents = events.reduce((groups, event) => {
        const date = new Date(event.start);
        const month = date.toLocaleString('default', { month: 'long', year: 'numeric' });
        if (!groups[month]) groups[month] = [];
        groups[month].push(event);
        return groups;
      }, {});

      Drupal.announce(`Grouping events by month.`);

      // Sort months chronologically.
      const sortedMonths = Object.keys(groupedEvents).sort((a, b) => new Date(a) - new Date(b));

      // Render all months, including past ones.
      this.renderMonths(sortedMonths, groupedEvents);
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
