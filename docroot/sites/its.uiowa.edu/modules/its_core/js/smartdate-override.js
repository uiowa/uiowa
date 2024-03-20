(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.smartDateOverride = {
    attach: function (context) {
      once('smartDateOverride', '.smartdate--widget select.field-duration', context).forEach(function (durationSelect) {
        durationSelect.addEventListener('change', function () {
          if (this.value === 'custom') {
            const wrapper = this.closest('.smartdate--widget');
            const endDate = wrapper.querySelector('.time-end.form-date');
            const endTime = wrapper.querySelector('.time-end.form-time');
            // Set end date and time to today's date.
            endDate.value = new Date().toISOString().split('T')[0];
            endTime.value = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: false});
          }
        });
      });
    }
  };

})(Drupal, once);
