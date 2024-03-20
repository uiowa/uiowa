(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.smartDateOverride = {
    attach: function (context) {
      once('smartDateOverride', '.smartdate--widget select.field-duration', context).forEach(function (durationSelect) {
        durationSelect.addEventListener('change', function () {
          if (this.value === 'custom') {
            const wrapper = this.closest('.smartdate--widget');
            const endDate = wrapper.querySelector('.time-end.form-date');
            // Set end date to today's date
            endDate.value = new Date().toISOString().split('T')[0];
          }
        });
      });
    }
  };

})(Drupal, once);
