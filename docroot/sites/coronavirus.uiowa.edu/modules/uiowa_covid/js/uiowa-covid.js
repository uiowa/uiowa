/**
 * @file
 * UIowa COVID behaviors.
 */

(($, Drupal, drupalSettings) => {
  // Query our shim API for data and update placeholders.
  Drupal.behaviors.uiowaCovid = {
    attach: (context, settings) => {
      $('.block-uiowa-covid').once('uiowaCovid').each(() => {
        $.ajax({
          url: settings.uiowaCovid.endpoint,
          dataType: "json",
          success: (data) => {
            for (const datum in data) {
              // Check for a matching ID first and then classes second.
              // @see: Drupal\uiowa_covid\Plugin\Block\CovidDataBlock::build().
              let element = document.getElementById(`uiowa-covid-${datum}`);

              if (element) {
                element.innerText = data[datum];
              }
              else {
                let elements = document.getElementsByClassName(`uiowa-covid-${datum}`);

                if (elements) {
                  for (const element of elements) {
                    element.innerText = data[datum];
                  }
                }
              }
            }
          },
          error: (request, status, error) => {
            let disclaimer = document.getElementById('uiowa-covid-disclaimer');

            if (disclaimer) {
              disclaimer.innerText = '<p>Unable to retrieve COVID data at this time. Please try again later.</p>';
            }

            let report = document.getElementById('uiowa-covid-report');

            if (report) {
              report.style.display = 'none';
            }
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
