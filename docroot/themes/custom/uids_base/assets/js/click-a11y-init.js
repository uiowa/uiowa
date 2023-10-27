import { applyClickA11y } from '../../uids/assets/js/click-a11y.js';

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.clickA11y = {
    attach: function (context, settings) {
      applyClickA11y('.click-container:not([data-uids-no-link])');
    }
  };
})(jQuery, Drupal);
