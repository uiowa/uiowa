(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.sitenow-academic-calendar', context).forEach(function (calendarEl) {
        const calendarSettings = drupalSettings.academicCalendar;
        const steps = calendarSettings.steps || 0;
        const includePastSessions = calendarSettings.includePastSessions || 0;
        const formEl = calendarEl.querySelector('#academic-calendar-filter-form');
        const formEls = getFormEls(formEl);

        // Initialize the AcademicCalendar object.
        const academicCalendar = new AcademicCalendar(
          calendarEl,
          steps,
          includePastSessions,
          getFilterValues()
        );

        // Fetch events and populate year filter.
        academicCalendar.fetchEvents().then(() => {
          populateYearFilter(academicCalendar.uniqueYears);
        });

        formEls.yearSelectEl.addEventListener('change', function() {
          updateFilterDisplay();
        });

        // Attach filter functionality to form elements.
        formEl.addEventListener('change', function (event) {
          if (['search', 'year', 'start_date', 'subsession', '#subsession', 'select', 'category'].includes(event.target.name)) {
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
        }

        // Function to update AcademicCalendar object based on form filters.
        function updateAcademicCalendarFromFilters() {
          const filterValues = getFilterValues();
          academicCalendar.searchTerm = filterValues.searchTerm;
          academicCalendar.startDate = filterValues.startDate;
          academicCalendar.selectedYear = filterValues.selectedYear;
          academicCalendar.selectedCategories = filterValues.selectedCategories;
          academicCalendar.showSubSessions = filterValues.showSubSessions;
        }

        // Function to get the values of the filters at call time.
        function getFilterValues() {
          return {
            'searchTerm' : formEls.searchTermEl.value.toLowerCase(),
            'startDate' : formEls.startDateEl.value,
            'selectedYear' : formEls.yearSelectEl.value,
            'selectedCategories' : formEls.categoryEl.value,
            'showSubSessions' : formEls.showSubSessionEl.checked,
          };
        }

        // Function to get the form elements for future use.
        function getFormEls(formEl) {
          return {
            'searchTermEl' : formEl.querySelector('.academic-calendar-search'),
            'startDateEl' : formEl.querySelector('.academic-calendar-start-date'),
            'yearSelectEl' : formEl.querySelector('select[name="year"]'),
            'categoryEl' : formEl.querySelector('select[name="category"]'),
            'resetButton' : formEl.querySelector('.js-form-reset'),
            'showSubSessionEl' : formEl.querySelector('#subsession'),
          }
        }

        // Function to populate the year filter dropdown.
        function populateYearFilter(years) {
          let yearBuffer = '<option value="">All Years</option>';
          const sortedYears = Array.from(years).sort((a, b) => a - b);

          sortedYears.forEach(year => {
            yearBuffer += `<option value="${year}">${year}</option>`;
          });

          formEls.yearSelectEl.innerHTML = yearBuffer;
        }
      });
    }
  };

  class AcademicCalendar {
    steps = 0;
    constructor(calendarEl, steps, includePastSessions, filterValues) {
      // Keep the HTML here so that we can add it all at once, preventing content refreshes.
      this.domOutput = document.createElement("div");
      this.allEvents = [];
      this.startDate = filterValues.startDate;
      // @todo remove endDate once approved by registrar.
      this.endDate = '';
      this.searchTerm = filterValues.searchTerm;
      this.steps = steps;
      this.includePastSessions = includePastSessions;
      this.selectedYear = filterValues.selectedYear;
      this.showSubSessions = filterValues.showSubSessions;
      this.selectedCategories = filterValues.selectedCategories;
      // Initialize variables to store all events and unique years.
      this.uniqueYears = new Set();
      //------------------------------//
      // Cache objects for frequently used elements.
      this.calendarContent = calendarEl.querySelector('#academic-calendar-content');
      this.spinner = this.calendarContent.querySelector('.fa-spinner');
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
      const fetchResults = await fetch(`/api/five-year-academic-calendar?subsession=1&start=${this.startDate}&end=${this.endDate}&steps=${this.steps}&includePastSessions=${this.includePastSessions}`)
        .then(response => response.json())
        .then(events => {
          this.allEvents = events;
          this.uniqueYears.clear();
          events.forEach((event) => {
            this.uniqueYears.add(new Date(event.start).getFullYear());
            event.domTree = this.parseCardDomString(event.rendered)
          });
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
      const today = new Date();
      const startDate = new Date(this.startDate);

      // Filter events based on search term, date range, and selected year.
      const filteredEvents = this.allEvents.filter(event => {
        const eventEnd = new Date(event.end);
        const startCheck = this.selectedYear ? true : (!startDate.valueOf() ? eventEnd >= today : eventEnd >= startDate);
        const matchesSubSession = event.subSession ? this.showSubSessions : true;

        const matchesSearch = !searchTerm ||
          event.title.toLowerCase().includes(searchTerm) ||
          event.description.toLowerCase().includes(searchTerm);

        const matchesYear = !this.selectedYear || new Date(event.start).getFullYear().toString() === this.selectedYear;

        let matchesCategories;
        if (this.selectedCategories === 'STUDENT') {
          matchesCategories = true;
        } else {
          matchesCategories = event.categories && Object.keys(event.categories).includes(this.selectedCategories);
        }

        return matchesSearch && startCheck && matchesYear && matchesCategories && matchesSubSession;
      });

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
        this.calendarContent.replaceChildren(...this.domOutput.childNodes)
        this.domOutput = document.createElement("div");

        Drupal.announce('No events found matching your criteria.');
        return;
      }

      Drupal.announce(`Displaying ${events.length} events based on filter criteria.`);

      // Sort events chronologically.
      events.sort((a, b) => new Date(a.start) - new Date(b.start));

      this.displayGroupedByYearAndSession(events);

      // Apply date hiding after all events have been added to the DOM
      this.addDateHiders(this.domOutput);

      this.calendarContent.replaceChildren(...this.domOutput.childNodes)
      this.domOutput = document.createElement("div");
    }

    addDateHiders(node) {
      const yearHeadings = node.querySelectorAll('h2');
      yearHeadings.forEach(yearHeading => {
        let currentElement = yearHeading.nextElementSibling;
        let datesFound = [];

        while (currentElement && currentElement.tagName !== 'H2') {
          if (currentElement.tagName === 'H3') {
            // New session, reset datesFound
            datesFound = [];
          }

          if (currentElement.tagName === 'H4' && this.showSubSessions) {
            // New subsession and showSubSessions is true, reset datesFound
            datesFound = [];
          }

          const date = currentElement.querySelector('.media--date');
          if (date) {
            const entryIndex = date.innerText;
            if (datesFound.includes(entryIndex)) {
              date.classList.add('element-invisible');
            } else {
              date.classList.remove('element-invisible');
              datesFound.push(entryIndex);
            }
          }

          currentElement = currentElement.nextElementSibling;
        }
      });
    }

    // Function to display events grouped by year and then by session.
    displayGroupedByYearAndSession(events) {
      Drupal.announce(`Grouping events by year and session.`);

      // Group events by year and session
      const groupedEvents = this.groupEventsByYearAndSession(events);

      // Sort years
      const sortedYears = Object.keys(groupedEvents).sort((a, b) => a - b);

      // Display the grouped and sorted events
      sortedYears.forEach(year => {
        this.renderYearHeading(year);

        const sessionsInYear = groupedEvents[year];
        const sortedSessionIds = Object.keys(sessionsInYear).sort((a, b) => a - b);

        sortedSessionIds.forEach(sessionId => {
          const { sessionDisplay, events, subsessions } = sessionsInYear[sessionId];
          this.renderSessionHeading(sessionDisplay, events);

          if (subsessions) {
            Object.entries(subsessions).forEach(([subSessionDisplay, subSessionEvents]) => {
              this.renderSubSessionHeading(subSessionDisplay, subSessionEvents);
            });
          }
        });
      });
    }

    // Helper function to group events by year and session
    groupEventsByYearAndSession(events) {
      return events.reduce((groups, event) => {
        const year = new Date(event.start).getFullYear();
        if (!groups[year]) {
          groups[year] = {};
        }

        if (event.subSession) {
          // This is a subsession event
          const mainSessionId = event.sessionId.split('-')[0]; // Assuming main session ID is the first part
          if (!groups[year][mainSessionId]) {
            groups[year][mainSessionId] = { sessionDisplay: event.sessionDisplay.split(' - ')[0], events: [], subsessions: {} };
          }
          if (!groups[year][mainSessionId].subsessions[event.sessionDisplay]) {
            groups[year][mainSessionId].subsessions[event.sessionDisplay] = [];
          }
          groups[year][mainSessionId].subsessions[event.sessionDisplay].push(event);
        } else {
          // This is a main session event
          if (!groups[year][event.sessionId]) {
            groups[year][event.sessionId] = { sessionDisplay: event.sessionDisplay, events: [], subsessions: {} };
          }
          groups[year][event.sessionId].events.push(event);
        }

        return groups;
      }, {});
    }

    // Function to render year heading
    renderYearHeading(year) {
      const yearHeading = document.createElement("h2");
      yearHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--extra', 'block-padding__top');
      yearHeading.innerText = year;
      this.domOutput.append(yearHeading);
    }

    // Function to render session heading
    renderSessionHeading(sessionDisplay, events) {
      const sessionHeading = document.createElement("h3");
      sessionHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--extra');
      sessionHeading.innerText = sessionDisplay;
      this.domOutput.append(sessionHeading);

      events.forEach(event => this.renderEvent(event));
    }

    // Function to render subsession heading
    renderSubSessionHeading(subSessionDisplay, events) {
      const subSessionHeading = document.createElement("h4");
      subSessionHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--medium');
      subSessionHeading.innerText = subSessionDisplay;
      this.domOutput.append(subSessionHeading);

      events.forEach(event => this.renderEvent(event));
    }

    // Function to render individual event.
    renderEvent(event) {
      this.domOutput.append(event.domTree);
    }
  }
})(Drupal, drupalSettings);
