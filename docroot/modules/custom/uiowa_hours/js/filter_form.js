(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.uiowa_hours_filter_form = {
    attach: function (context, settings) {
      $('.uiowa-hours-filter-form').once('filter_form').each(function () {
        let isDisabled = $('input[type="submit"]', this).prop('disabled');

        if (isDisabled) {
          $('.uiowa-hours-container', this).html('<p>Placeholder for "Hours" data</p>');
        }
        else {
          $('input[type="submit"]', this).mousedown();
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
