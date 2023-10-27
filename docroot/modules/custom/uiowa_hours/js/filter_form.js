(function (Drupal, $, once) {
  'use strict';
  Drupal.behaviors.uiowa_hours_filter_form = {
    attach: function (context) {
      $(once('filter_form', '.uiowa-hours-filter-form', context)).each(function () {
        let isDisabled = $('input[type="submit"]', this).prop('disabled');

        if (isDisabled) {
          let hoursConfig = $('input[name="block_config"]', this).val().split(' ');
          $('.uiowa-hours-container', this).html('<p>Placeholder for ' + hoursConfig[0] + ' data</p>');
        }
        else {
          let date = new Date();
          let today = new Date(date.getTime() - (date.getTimezoneOffset() * 60000 ))
            .toISOString()
            .split("T")[0];
          $('input[type="date"]', this).val(today);
          $('input[type="submit"]', this).mousedown();
        }
      });
    }
  };
})(Drupal, jQuery, once);
