import { applyAccordion } from '../../uids/assets/js/accordion.js';

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.uidsAccordion = {
    attach: function (context, settings) {
      applyAccordion('.accordion:not([data-accordion-processed])', context);

      const accordions = context.querySelectorAll('.accordion:not([data-accordion-processed])');
      accordions.forEach(accordion => {
        accordion.setAttribute('data-accordion-processed', 'true');
      });
    }
  };
})(jQuery, Drupal);
