(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.sitenow-academic-calendar', context).forEach(function (calendarEl) {
        const calendarSettings = drupalSettings.academicCalendar;
        const steps = calendarSettings.steps || 0;
        const yearOptions = calendarSettings.yearOptions || {};
        const defaultYear = calendarSettings.defaultYear;
        const formEl = calendarEl.querySelector('#academic-calendar-filter-form');
        const formEls = getFormEls(formEl);

        // Initialize the AcademicCalendar object.
        const academicCalendar = new AcademicCalendar(
          calendarEl,
          steps,
          getFilterValues(),
          yearOptions,
          defaultYear
        );

        // Fetch events
        academicCalendar.fetchEvents();

        formEls.startYearSelectEl.addEventListener('change', function() {
          updateFilterDisplay();
        });

        // Attach filter functionality to form elements.
        formEl.addEventListener('change', function (event) {
          if (['start_year', 'start_date', 'subsession', '#subsession'].includes(event.target.name)) {
            updateFilterDisplay();
          }
        });

        // Attach date filter functionality.
        context.querySelectorAll('.academic-calendar-start-date, .academic-calendar-end-date').forEach(input => {
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

        function updateAcademicCalendarFromFilters() {
          const filterValues = getFilterValues();
          academicCalendar.startDate = filterValues.startDate;
          academicCalendar.selectedYear = filterValues.selectedYear;
          academicCalendar.showSubSessions = filterValues.showSubSessions;
        }

        function getFilterValues() {
          return {
            'startDate' : formEls.startDateEl.value,
            'selectedYear' : formEls.startYearSelectEl.value,
            'showSubSessions' : formEls.showSubSessionEl.checked,
          };
        }

        function getFormEls(formEl) {
          return {
            'startDateEl' : formEl.querySelector('.academic-calendar-start-date'),
            'startYearSelectEl' : formEl.querySelector('select[name="start_year"]'),
            'resetButton' : formEl.querySelector('.js-form-reset'),
            'showSubSessionEl' : formEl.querySelector('#subsession'),
          }
        }
      });
    }
  };

  class AcademicCalendar {
    constructor(calendarEl, steps, filterValues, yearOptions, defaultYear) {
      this.domOutput = document.createElement("div");
      this.allEvents = [];
      this.startDate = filterValues.startDate;
      this.endDate = '';
      this.steps = steps;
      this.yearOptions = yearOptions;
      this.defaultYear = defaultYear;
      this.selectedYear = filterValues.selectedYear || this.defaultYear;
      this.showSubSessions = filterValues.showSubSessions;
      this.calendarContent = calendarEl.querySelector('#academic-calendar-content');
      this.spinner = this.calendarContent.querySelector('.fa-spinner');
    }

    async fetchEvents() {
      this.toggleSpinner();
      Drupal.announce('Fetching events.');
      try {
        const response = await fetch(`/api/five-year-academic-calendar?subsession=1&start=${this.startDate}&steps=10&includePastSessions=1`);
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
      this.toggleSpinner();
    }

    toggleSpinner() {
      if (this.spinner) {
        this.spinner.style.display = this.spinner.style.display === 'block' ? 'none' : 'block';
      }
    }

    parseCardDomString(string) {
      const domTreeEl = new DOMParser().parseFromString(string, "text/html");
      return domTreeEl.querySelector('.card');
    }

    filterAndDisplayEvents() {
      const selectedYearRange = this.yearOptions[this.selectedYear].split(' - ');
      const startYear = parseInt(selectedYearRange[0]);
      const endYear = parseInt('20' + selectedYearRange[1]);

      const filteredEvents = this.allEvents.filter(event => {
        const eventDate = new Date(event.start);
        const eventYear = eventDate.getFullYear();
        const eventMonth = eventDate.getMonth();

        // Academic year is considered from August 1st to July 31st
        const isInAcademicYear = (eventYear === startYear && eventMonth >= 8) ||
          (eventYear === endYear && eventMonth < 8);

        const matchesSubSession = event.subSession ? this.showSubSessions : true;
        const matchesCategories = event.categories && Object.keys(event.categories).includes('STUDENT');

        return isInAcademicYear && matchesSubSession && matchesCategories;
      });

      this.displayEvents(filteredEvents);
    }

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

    addDateHiders(node) {
      const yearHeadings = node.querySelectorAll('h2');
      yearHeadings.forEach(yearHeading => {
        let currentElement = yearHeading.nextElementSibling;
        let datesFound = [];

        while (currentElement && currentElement.tagName !== 'H2') {
          if (currentElement.tagName === 'H3') {
            datesFound = [];
          }

          if (currentElement.tagName === 'H4' && this.showSubSessions) {
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

    groupEventsByYearAndSession(events) {
      const selectedYearRange = this.yearOptions[this.selectedYear].split(' - ');
      const startYear = parseInt(selectedYearRange[0]);
      const endYear = parseInt('20' + selectedYearRange[1]);

      return events.reduce((groups, event) => {
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

        // Ensure the event is in the correct academic year
        if ((sessionName === 'Fall' && eventYear !== startYear) ||
          (sessionName === 'Winter' && (eventMonth === 12 && eventYear === startYear || eventMonth === 1 && eventYear === startYear + 1)) ||
          (sessionName === 'Spring' && eventYear !== endYear) ||
          (sessionName === 'Summer' && eventYear !== endYear)) {
          return groups; // Skip this event if it's not in the correct year
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
    }

    renderYearHeading(year) {
      const yearHeading = document.createElement("h2");
      yearHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--extra', 'block-padding__top');
      yearHeading.innerText = `Academic Year ${this.yearOptions[year]}`;
      this.domOutput.append(yearHeading);
    }

    renderSessionHeading(sessionDisplay, events) {
      const sessionHeading = document.createElement("h3");
      sessionHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--extra');
      sessionHeading.innerText = sessionDisplay;
      this.domOutput.append(sessionHeading);

      events.forEach(event => this.renderEvent(event));
    }

    renderSubSessionHeading(subSessionDisplay, events) {
      const subSessionHeading = document.createElement("h4");
      subSessionHeading.classList.add('headline', 'headline--serif', 'block-margin__bottom--medium');
      subSessionHeading.innerText = subSessionDisplay;
      this.domOutput.append(subSessionHeading);

      events.forEach(event => this.renderEvent(event));
    }

    renderEvent(event) {
      this.domOutput.append(event.domTree);
    }
  }
})(Drupal, drupalSettings);
