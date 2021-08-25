/**
 * @file
 * UIowa COVID behaviors.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Query our shim API for data and update placeholders.
   */
  Drupal.behaviors.uiowaCovid = {
    attach: function (context, settings) {
      $('.block-uiowa-covid').once('uiowaCovid').each(() => {
        fetch(settings.uiowaCovid.endpoint)
          .then(response => response.json())
          .then(data => {
            for (const datum in data) {
              // Each placeholder ID should match the JSON key.
              // @see: Drupal\uiowa_covid\Plugin\Block\CovidDataBlock::build().
              let element = document.getElementById(`uiowa-covid-${datum}`);

              if (element) {
                element.innerText = data[datum];
              }
            }
          })
          .catch((error) => {
            let element = document.getElementById('uiowa-covid-disclaimer');

            if (element) {
              element.innerText = '<p>Unable to retrieve COVID data at this time. Please try again later.</p>';
            }
          });
      });
    }
  };

} (jQuery, Drupal));
