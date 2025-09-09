(function (Drupal) {
  "use strict";

  Drupal.behaviors.slideDurationToggle = {
    attach: function (context) {
      const hasVideo = context.querySelector('[class*="slide-video"]');
      const durationField = context.querySelectorAll(
        '[data-drupal-selector="edit-field-slide-duration"]',
      );
      const durationOtherField = context.querySelectorAll(
        '[data-drupal-selector="edit-field-slide-duration-other-wrapper"]',
      );

      // Merge both duration elements.
      const allDurationFields = [...durationField, ...durationOtherField];

      allDurationFields.forEach((element) => {
        const container = element.closest(".js-form-item") || element;

        if (hasVideo) {
          container.classList.add("js-hide");
          container.tabIndex = -1;
          container.setAttribute("aria-hidden", "true");
        } else {
          container.classList.remove("js-hide");
          container.removeAttribute("tabindex");
          container.removeAttribute("aria-hidden");
        }
      });
    },
  };
})(Drupal);
