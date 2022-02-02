(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.uiowa_hours_filter_form = {
    attach: function (context, settings) {
      $('.uiowa-hours-filter-form').once('filter_form').each(function () {
        let isDisabled = $('input[type="submit"]', this).prop('disabled');

        if (isDisabled) {
          let hoursConfig = $('input[name="block_config"]', this).val().split(' ');
          $('.uiowa-hours-container', this).html('<p>Placeholder for ' + hoursConfig[0] + ' data</p>');
        }
        else {
          let today = new Date().toLocaleDateString('en-CA', {timeZone: 'America/Chicago'});
          $('input[type="date"]', this).val(today);
          $('input[type="submit"]', this).mousedown();
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
