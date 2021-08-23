/**
 * @file
 * UIowa COVID behaviors.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Behavior description.
   */
  Drupal.behaviors.uiowaCovid = {
    attach: function (context, settings) {
      $('.block-uiowa-covid').once('uiowaCovid').each(() => {
        fetch(settings.uiowaCovid.endpoint)
          .then(response => response.json())
          .then(data => {
            for (const datum in data) {
              let element = document.getElementById(`uiowa-covid-${datum}`);
              element.innerText = data[datum];
            }
          });
      });
    }
  };

} (jQuery, Drupal));
