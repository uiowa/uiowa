(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.uiowa_hours_finishedinput = {
    attach: function (context, settings) {
      var typingTimer;
      var delay = 1100;

      $('.form-date').once('finished_input').each(function () {
        $(this).on('input', function () {
          clearTimeout(typingTimer);
          if ($(this).val()) {
            var trigid = $(this);
            typingTimer = setTimeout(function () {
              trigid.triggerHandler('finishedinput');
            }, delay);
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
