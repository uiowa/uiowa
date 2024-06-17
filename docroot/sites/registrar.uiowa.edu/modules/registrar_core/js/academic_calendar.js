(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.academic-calendar', context).forEach(function (element) {
        const calendar = new FullCalendar.Calendar(element, {
          initialView: 'dayGridMonth',
          views: {
            listMonth: {
              buttonText: 'list month'
            }
          },
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
          },
          events: {
            url: '/api/academic-calendar',
            method: 'GET',
            extraParams: function() {
              const $form = $('#academic-calendar-filter-form', context);
              const categories = $form.find('select[name="category[]"]').val() || ['STUDENT'];
              const steps = drupalSettings.academicCalendar.steps || 0;
              return {
                category: categories,
                subsession: $form.find('input[name="subsession"]').is(':checked') ? '1' : '0',
                steps: steps
              };
            },
          },
          eventDidMount: function(info) {
            const { event, el } = info;

            // Add tabindex to the event element
            el.tabIndex = 0;

            // Add keydown event listener to the event element
            el.addEventListener('keydown', function(e) {
              if (e.key === 'Enter') {
                info.jsEvent = e;
                calendar.trigger('eventClick', info);
              }
            });
          },
          eventClick: function(info) {
            const { event, el } = info;

            // Create popover content
            const popoverContent = `
              <div class="event-popover-content">
                <h3>${event.extendedProps.popoverTitle || event.title}</h3>
                <div>${event.extendedProps.popoverContent || ''}</div>
              </div>
            `;

            // Generate a unique ID for the tooltip
            const tooltipId = `tooltip-${event.id}`;

            // Set the aria-describedby attribute on the clicked element
            el.setAttribute('aria-describedby', tooltipId);

            // Create popover element
            const popover = new Tooltip(el, {
              title: popoverContent,
              placement: 'top',
              trigger: 'manual',
              container: 'body',
              html: true,
              template: `<div class="tooltip" role="tooltip" id="${tooltipId}"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>`
            });

            popover.show();
            // Hide the popover when clicking outside of it
            const hidePopover = function(event) {
              if (!el.contains(event.target)) {
                popover.hide();
                document.removeEventListener('click', hidePopover);
              }
            };

            document.addEventListener('click', hidePopover);

            // Hide the popover when tabbing away from it
            document.addEventListener('keydown', function(e) {
              if (e.key === 'Tab') {
                popover.hide();
                document.removeEventListener('keydown', this);
              }
            });
          },
          displayEventTime: false,
          handleWindowResize: true, // Allow FullCalendar to respond to window resize
          windowResizeDelay: 100, // Delay before handling the window resize event (in milliseconds),
          windowResize: function(view) {
            switchView();
          }
        });

        calendar.render();

        // Attach filter functionality
        $('#academic-calendar-filter-form', context).on('change', 'select, input', function() {
          calendar.refetchEvents();
        });

        // Handle form submission
        $('#academic-calendar-filter-form', context).on('submit', function(e) {
          e.preventDefault();
          calendar.refetchEvents();
        });

        // Previous button functionality
        $('.fc-prev-button').on('click', function() {
          const currentDate = calendar.getDate();
          const firstSessionStart = getFirstSessionStart();

          if (currentDate <= firstSessionStart) {
            $(this).prop('disabled', true);
          } else {
            $(this).prop('disabled', false);
            $('.fc-next-button').prop('disabled', false);
          }
        });

        // Next button functionality
        $('.fc-next-button').on('click', function() {
          const currentDate = calendar.getDate();
          const oneYearFromNow = new Date();
          oneYearFromNow.setFullYear(oneYearFromNow.getFullYear() + 1);

          if (currentDate >= oneYearFromNow) {
            $(this).prop('disabled', true);
          } else {
            $(this).prop('disabled', false);
            $('.fc-prev-button').prop('disabled', false);
          }
        });

        // Function to switch the view based on device type
        function switchView() {
          const isMobile = window.matchMedia('(max-width: 767px)').matches;
          if (isMobile) {
            calendar.changeView('listMonth');
          } else {
            calendar.changeView('dayGridMonth');
          }
        }

        // Check device type on initial load
        switchView();

        // Attach event listener for window resize
        window.addEventListener('resize', switchView);

        // Function to get the start date of the first session before the current date
        function getFirstSessionStart() {
          const currentSession = drupalSettings.academicCalendar.currentSession;
          const sessions = drupalSettings.academicCalendar.sessions;

          const currentSessionIndex = sessions.findIndex(session => session.id === currentSession.id);
          let firstSessionStart = new Date(currentSession.startDate);

          for (let i = currentSessionIndex - 1; i >= 0; i--) {
            const session = sessions[i];
            const sessionStart = new Date(session.startDate);
            if (sessionStart < firstSessionStart) {
              firstSessionStart = sessionStart;
            }
          }

          return firstSessionStart;
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
