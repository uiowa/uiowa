(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.academicCalendar = {
    attach: function (context, settings) {
      once('academicCalendar', '.academic-calendar', context).forEach(function (element) {
        const $eventList = $('<div class="list-container list-container--list"><div class="view-content list-container__inner"></div></div>');
        $(element).append($eventList);

        function fetchAndDisplayEvents() {
          const $form = $('#academic-calendar-filter-form', context);
          const categories = $form.find('select[name="category[]"]').val() || ['STUDENT'];
          const subsession = $form.find('input[name="subsession"]').is(':checked') ? '1' : '0';
          const steps = drupalSettings.academicCalendar.steps || 0;

          $.ajax({
            url: '/api/academic-calendar',
            method: 'GET',
            data: {
              category: categories,
              subsession: subsession,
              steps: steps
            },
            success: function(events) {
              const $container = $eventList.find('.view-content');
              $container.empty();

              // Sort events chronologically
              events.sort((a, b) => new Date(a.start) - new Date(b.start));

              // Group events by sessionDisplay
              const groupedEvents = events.reduce((groups, event) => {
                const group = groups[event.sessionDisplay] || [];
                group.push(event);
                groups[event.sessionDisplay] = group;
                return groups;
              }, {});

              // Render grouped events
              Object.entries(groupedEvents).forEach(([sessionDisplay, events]) => {
                $container.append(`<h2 class="headline headline--serif block-margin__bottom--extra block-padding__top">${sessionDisplay}</h2>`);
                events.forEach(renderEvent);
              });
            },
            error: function(xhr, status, error) {
              console.error('Error fetching events:', error);
              $eventList.html('<div>Error loading events. Please try again later.</div>');
            }
          });
        }

        function renderEvent(event) {
          const startDate = new Date(event.start);
          const endDate = new Date(event.end);
          const monthAbbr = startDate.toLocaleString('default', { month: 'short' });
          const startDay = startDate.getDate();
          const formattedStartDate = startDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

          let dateDisplay;
          if (startDate.toDateString() === endDate.toDateString()) {
            dateDisplay = `<time datetime="${event.start}" class="datetime">${formattedStartDate}</time>`;
          } else {
            const formattedEndDate = endDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            dateDisplay = `
      <time datetime="${event.start}" class="datetime">${formattedStartDate}</time>
      -
      <time datetime="${event.end}" class="datetime">${formattedEndDate}</time>
    `;
          }

          const $eventItem = $(`
    <div class="card--layout-left borderless click-container block--word-break card">
      <div class="media--circle media--border media--small media">
        <div class="media__inner">
          <div class="media media--type-image media--view-mode-large__square">
            <div class="upcoming-date">
              <span class="upcoming-month">${monthAbbr}</span>
              <span class="upcoming-day">${startDay}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="card__body">
        <header>
          <h3 class="headline headline--serif default">
            <a href="#" class="click-target">
              <span class="headline__heading">${event.title}</span>
            </a>
          </h3>
        </header>
        <div class="card__details">
          <div class="card__meta">
            <div class="fa-field-item field field--name-field-event-when field--type-smartdate field--label-visually_hidden">
              <div class="field__label visually-hidden">When</div>
              <span role="presentation" class="field__icon fas fa-calendar far"></span>
              <div class="field__item">
                ${dateDisplay}
              </div>
            </div>
          </div>
          <div class="card__meta">
          <div class="field__item">${event.description}</div>
</div>
        </div>
        <div class="clearfix text-formatted field field--name-body field--type-text-with-summary field--label-visually_hidden">
          <div class="field__label visually-hidden">Description</div>

        </div>
      </div>
    </div>
  `);

          $eventList.find('.view-content').append($eventItem);
        }

        // Initial fetch
        fetchAndDisplayEvents();

        // Attach filter functionality
        $('#academic-calendar-filter-form', context).on('change', 'select, input', function() {
          fetchAndDisplayEvents();
        });

        // Handle form submission
        $('#academic-calendar-filter-form', context).on('submit', function(e) {
          e.preventDefault();
          fetchAndDisplayEvents();
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
