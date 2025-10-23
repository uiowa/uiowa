(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.smartDateOverride = {
    attach: function (context) {
      once('smartDateOverride', '.smartdate--widget select.field-duration', context).forEach(function (durationSelect) {
        const wrapper = durationSelect.closest('.smartdate--widget');

        // Hide end date and time if duration is set to 0.
        if (+durationSelect.value === 0) {
          toggleEndDisplay(wrapper, true);
        }

        // Add an event listener to the duration select.
        durationSelect.addEventListener('change', function () {
          if (this.value === 'custom') {
            const endDate = wrapper.querySelector('.time-end.form-date');
            const endTime = wrapper.querySelector('.time-end.form-time');
            // Set end date and time to today's date.
            endDate.value = new Date().toISOString().split('T')[0];
            endTime.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
            // Show the end display.
            toggleEndDisplay(wrapper, false);
          }
          else if (+this.value === 0) {
            // Hide the end display when duration is set to 0.
            toggleEndDisplay(wrapper, true);
          }
        });
      });

      /**
       * Toggles the end date and time display.
       *
       * @param wrapper
       * @param hide
       */
      function toggleEndDisplay(wrapper, hide = true) {
        // A lot of this is copied from smart_date.js. That JS does not expose
        // these functions in a way that we can directly access them.
        const endDate = wrapper.querySelector('.time-end.form-date');
        const endTime = wrapper.querySelector('.time-end.form-time');
        const separator = wrapper.querySelector('.smartdate--separator');
        let displayVal = 'none';
        if (!hide) {
          displayVal = '';
        }

        // Hide end date and time.
        endDate.parentElement.style.display = displayVal;
        endTime.parentElement.style.display = displayVal;
        // Hide wrapper labels.
        wrapper
          .querySelectorAll('.form-type--date label.form-item__label')
          .forEach(function (label) {
            label.style.display = displayVal;
          });
        // Hide the separator.
        if (separator) {
          separator.style.display = displayVal;
        }
      }
    }
  };

})(Drupal, once);
