(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.sitenow-academic-calendar', context).forEach(function (calendarEl) {
        const calendarSettings = drupalSettings.academicCalendar;
        const yearOptions = calendarSettings.yearOptions || {};
        const defaultYear = calendarSettings.defaultYear;
        const formEl = calendarEl.querySelector('#academic-calendar-filter-form');
        const formEls = getFormEls(formEl);

        // Function to get year range from year ID.
        function getYearRangeFromId(yearId) {
          return yearOptions[yearId] || '';
        }

        // Check for hash in URL and update the year if it matches.
        function checkUrlHash() {
          const hash = window.location.hash.substring(1);
          if (hash && yearOptions.hasOwnProperty(hash)) {
            formEls.startYearSelectEl.value = hash;
            return hash;
          }
          return defaultYear;
        }

        // Update URL hash with year range.
        function updateUrlHash(yearId) {
          const yearRange = getYearRangeFromId(yearId);
          history.replaceState(null, null, `#${yearRange}`);
        }

        // Initialize the AcademicCalendar object with the year from URL or default.
        const initialYear = checkUrlHash();
        updateUrlHash(initialYear);
        const academicCalendar = new AcademicCalendar(
          calendarEl,
          getFilterValues(),
          yearOptions,
          defaultYear
        );

        // Fetch events.
        academicCalendar.fetchEvents();

        formEls.startYearSelectEl.addEventListener('change', function() {
          updateFilterDisplay();
          updateUrlHash(this.value);
        });

        // Handle form submission.
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
            // Clear URL hash on reset.
            history.pushState("", document.title, window.location.pathname + window.location.search);
          });
        }

        // Listen for hashchange events.
        window.addEventListener('hashchange', function() {
          const newYear = checkUrlHash();
          if (newYear !== academicCalendar.selectedYear) {
            formEls.startYearSelectEl.value = newYear;
            updateFilterDisplay(true);
          }
        });

        function updateFilterDisplay(call = false) {
          if (call) {
            updateAcademicCalendarFromFilters();
            academicCalendar.filterAndDisplayEvents.call(academicCalendar);
          }
          else {
            updateAcademicCalendarFromFilters();
            academicCalendar.filterAndDisplayEvents();
            if (formEls.resetButton) {
              formEls.resetButton.style.display = 'inline-block';
            }
          }
        }

        function updateAcademicCalendarFromFilters() {
          const filterValues = getFilterValues();
          academicCalendar.selectedYear = filterValues.selectedYear;
        }

        function getFilterValues() {
          return {
            'selectedYear': formEls.startYearSelectEl.value,
          };
        }

        function getFormEls(formEl) {
          return {
            'startYearSelectEl': formEl.querySelector('select[name="start_year"]'),
            'resetButton': formEl.querySelector('.js-form-reset'),
          }
        }
      });
    }
  };

  class AcademicCalendar {
    constructor(calendarEl, filterValues, yearOptions, defaultYear) {
      this.domOutput = document.createElement("div");
      this.allEvents = [];
      this.yearOptions = yearOptions;
      this.defaultYear = defaultYear;
      this.selectedYear = filterValues.selectedYear || this.defaultYear;
      this.calendarContent = calendarEl.querySelector('#academic-calendar-content');
    }

    async fetchEvents() {
      Drupal.announce('Fetching events.');
      try {
        const response = await fetch(`/api/five-year-academic-calendar`);
        const events = await response.json();
        this.allEvents = events;
        events.forEach((event) => {
          event.domTree = this.parseCardDomString(event.rendered)
        });
        this.filterAndDisplayEvents();
      } catch (error) {
        console.error('Error fetching events:', error);
        this.domOutput = document.createElement("div");
        const eventsError = document.createElement("div");
        eventsError.innerText = 'Error loading events. Please try again later.';
        this.domOutput.append(eventsError);
        Drupal.announce('Error loading events. Please try again later.');
      }
    }

    parseCardDomString(string) {
      const domTreeEl = new DOMParser().parseFromString(string, "text/html");
      return domTreeEl.querySelector('.card');
    }

    // Filters and displays events based on the current filter settings.
    filterAndDisplayEvents() {
      const selectedYearRange = this.yearOptions[this.selectedYear].split('-');
      const startYear = parseInt(selectedYearRange[0]);
      const endYear = parseInt('20' + selectedYearRange[1]);

      const filteredEvents = this.allEvents.filter(event => {
        const eventDate = new Date(event.start);
        const eventYear = eventDate.getFullYear();
        const eventMonth = eventDate.getMonth();

        return (eventYear === startYear && eventMonth >= 7) ||
          (eventYear === endYear && (eventMonth < 8 || (eventMonth === 7 && eventDate.getDate() <= 31))) ||
          (eventYear > startYear && eventYear < endYear);
      });

      this.displayEvents(filteredEvents);
    }

    // Displays the filtered events.
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

      events.sort((a, b) => new Date(a.start) - new Date(b.start));

      this.displayGroupedByYearAndSession(events);

      this.addDateHiders(this.domOutput);

      this.calendarContent.replaceChildren(...this.domOutput.childNodes)
      this.domOutput = document.createElement("div");
    }

    // Adds date hiders to avoid repetitive date displays.
    addDateHiders(node) {
      const yearHeadings = node.querySelectorAll('h2');
      yearHeadings.forEach(yearHeading => {
        let currentElement = yearHeading.nextElementSibling;
        let datesFound = [];

        while (currentElement && currentElement.tagName !== 'H2') {
          if (currentElement.tagName === 'H3') {
            datesFound = [];
          }

          if (currentElement.tagName === 'H4') {
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

    // Displays events grouped by year and session.
    displayGroupedByYearAndSession(events) {
      Drupal.announce(`Grouping events by year and session.`);

      const groupedEvents = this.groupEventsByYearAndSession(events);

      this.renderYearHeading(this.selectedYear);

      const sessionOrder = ['Fall', 'Winter', 'Spring', 'Summer'];

      sessionOrder.forEach(session => {
        if (groupedEvents[session]) {
          const { sessionDisplay, events, subsessions } = groupedEvents[session];
          this.renderSessionHeading(sessionDisplay, events);

          if (subsessions) {
            Object.entries(subsessions).forEach(([subSessionDisplay, subSessionEvents]) => {
              this.renderSubSessionHeading(subSessionDisplay, subSessionEvents);
            });
          }
        }
      });
    }

    // Groups events by year and session.
    groupEventsByYearAndSession(events) {
      const selectedYearRange = this.yearOptions[this.selectedYear].split('-');
      const startYear = parseInt(selectedYearRange[0]);
      const endYear = parseInt('20' + selectedYearRange[1]);

      const groups = events.reduce((groups, event) => {
        const eventDate = new Date(event.start);
        const eventYear = eventDate.getFullYear();
        const eventMonth = eventDate.getMonth();
        const sessionName = event.sessionDisplay.split(' ')[0];

        let sessionYear;
        if (sessionName === 'Fall' || sessionName === 'Winter') {
          sessionYear = startYear;
        } else if (sessionName === 'Spring' || sessionName === 'Summer') {
          sessionYear = endYear;
        }

        // Ensure the event is in the correct academic year.
        if ((sessionName === 'Fall' && eventYear !== startYear) ||
          (sessionName === 'Winter' && (eventMonth === 12 && eventYear === startYear || eventMonth === 1 && eventYear === startYear + 1)) ||
          (sessionName === 'Spring' && eventYear !== endYear) ||
          (sessionName === 'Summer' && eventYear !== endYear)) {
          return groups;
        }

        const sessionDisplay = `${sessionName} ${sessionYear}`;

        if (!groups[sessionName]) {
          groups[sessionName] = {
            sessionDisplay: sessionDisplay,
            events: [],
            subsessions: {}
          };
        }

        if (event.subSession) {
          const subSessionKey = `${event.sessionDisplay} ${sessionYear}`;
          if (!groups[sessionName].subsessions[subSessionKey]) {
            groups[sessionName].subsessions[subSessionKey] = [];
          }
          groups[sessionName].subsessions[subSessionKey].push(event);
        } else {
          groups[sessionName].events.push(event);
        }

        return groups;
      }, {});

      // Sort summer subsessions.
      if (groups['Summer'] && groups['Summer'].subsessions) {
        const sortedSubsessions = {};
        const subsessionEntries = Object.entries(groups['Summer'].subsessions);

        subsessionEntries.sort((a, b) => {
          const getWeekNumber = (str) => {
            const match = str.match(/(\d+)\s*(Week|wk)/i);
            return match ? parseInt(match[1]) : 0;
          };
          const weekA = getWeekNumber(a[0]);
          const weekB = getWeekNumber(b[0]);

          return weekA - weekB;
        });

        subsessionEntries.forEach(([key, value]) => {
          sortedSubsessions[key] = value;
        });

        groups['Summer'].subsessions = sortedSubsessions;
      }

      return groups;
    }

    // Renders the year heading.
    renderYearHeading(year) {
      const yearHeading = document.createElement("h2");
      yearHeading.classList.add('headline', 'headline--underline', 'headline--serif', 'block-margin__bottom--extra', 'block-padding__top');
      yearHeading.innerText = `Academic Year ${this.yearOptions[year]}`;
      this.domOutput.append(yearHeading);
    }

    // Renders the session heading and its events.
    renderSessionHeading(sessionDisplay, events) {
      const sessionHeading = document.createElement("h3");
      sessionHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--extra');
      sessionHeading.innerText = sessionDisplay;
      this.domOutput.append(sessionHeading);

      events.forEach(event => this.renderEvent(event));
    }

    // Renders the sub-session heading and its events.
    renderSubSessionHeading(subSessionDisplay, events) {
      const subSessionHeading = document.createElement("h4");
      subSessionHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--extra');
      // Remove the year from the subSessionDisplay.
      const displayParts = subSessionDisplay.split(' ');
      displayParts.pop();
      const cleanedDisplay = displayParts.join(' ');
      subSessionHeading.innerText = cleanedDisplay;
      this.domOutput.append(subSessionHeading);

      events.forEach(event => this.renderEvent(event));
    }

    renderEvent(event) {
      this.domOutput.append(event.domTree);
    }
  }
})(Drupal, drupalSettings);
