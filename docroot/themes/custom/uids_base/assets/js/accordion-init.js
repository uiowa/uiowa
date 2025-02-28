import { applyAccordion } from '../../uids/assets/js/accordion.js';

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.initAccordions = {
    attach: function (context, settings) {
      applyAccordion('.accordion');
    }
  };
})(jQuery, Drupal);
