(function (Drupal, $, once) {
  'use strict';
  Drupal.behaviors.uiowa_hours_finishedinput = {
    attach: function (context) {
      var typingTimer;
      var delay = 1100;

      $(once('finished_input', '.form-date', context)).each(function () {
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
})(Drupal, jQuery, once);
