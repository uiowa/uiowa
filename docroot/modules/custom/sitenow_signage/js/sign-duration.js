(function (Drupal) {
  'use strict';

  Drupal.behaviors.signDurationToggle = {
    attach: function (context) {
      function toggleDurationFields() {
        context
          .querySelectorAll(
            '[data-drupal-selector*="field-sign-slides"] .ief-form',
          )
          .forEach(function (container) {
            var hasVideo =
              container.querySelectorAll('.paragraph-type--slide-video')
                .length > 0;

            // Handle both main duration field and duration "other" field
            const durationFields = [
              ...container.querySelectorAll(
                '[data-drupal-selector*="field-slide-duration-wrapper"]',
              ),
              ...container.querySelectorAll(
                '.field--name-field-slide-duration-other.field--widget-number',
              ),
            ];

            durationFields.forEach(function (field) {
              if (hasVideo) {
                // Hide.
                field.classList.add('js-hide');
                field.tabIndex = -1;
                field.setAttribute('aria-hidden', 'true');
              } else {
                // Show.
                field.classList.remove('js-hide');
                field.removeAttribute('tabindex');
                field.removeAttribute('aria-hidden');
              }
            });
          });
      }

      setTimeout(toggleDurationFields, 300);
    },
  };
})(Drupal);
