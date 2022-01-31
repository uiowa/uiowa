(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.uiowa_hours_filter_form = {
    attach: function (context, settings) {
      $('.uiowa-hours-filter-form').once('filter_form').each(function () {

      });
    }
  };
})(jQuery, Drupal, drupalSettings);
