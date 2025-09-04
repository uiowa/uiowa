(function (Drupal) {
  'use strict';

  Drupal.behaviors.slideDurationToggle = {
    attach: function (context) {
      // Check if video elements exist.
      var hasVideo = context.querySelector('[class*="slide-video"]');

      // Show/hide duration field.
      var durationFields = context.querySelectorAll('[data-drupal-selector="edit-field-slide-duration"]');
      durationFields.forEach(field => {
        var container = field.closest('.js-form-item') || field;

        if (hasVideo) {
          // Hide.
          container.classList.add('js-hide');
          container.tabIndex = -1;
          container.setAttribute('aria-hidden', 'true');
        } else {
          // Show.
          container.classList.remove('js-hide');
          container.removeAttribute('tabindex');
          container.removeAttribute('aria-hidden');
        }
      });
    }
  };

})(Drupal);
