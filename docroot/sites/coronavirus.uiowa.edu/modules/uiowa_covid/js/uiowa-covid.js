/**
 * @file
 * UIowa COVID behaviors.
 *
 * Using jQuery here since it was deemed necessary for this to work in IE11.
 */

(function ($, Drupal, drupalSettings) {
  // Query our shim API for data and update placeholders.
  Drupal.behaviors.uiowaCovid = {
    attach: function (context, settings) {
      $('.block-uiowa-covid').once('uiowaCovid').each(function () {
        $.ajax({
          url: settings.uiowaCovid.endpoint,
          dataType: "json",
          success: function (data) {
            $.each(data, function (key, value) {
              // Check for a matching ID first and then classes second.
              // @see: Drupal\uiowa_covid\Plugin\Block\CovidDataBlock::build().
              let element = $('#uiowa-covid-' + key);

              if (element.length) {
                $(element).text(value);
              }
              else {
                $('.uiowa-covid-' + key).each(function() {
                  $(this).text(value);
                });
              }
            });
          },
          error: function (request, status, error) {
            $('#uiowa-covid-disclaimer').text('<p>Unable to retrieve COVID data at this time. Please try again later.</p>');
            $('#uiowa-covid-report').hide();
          }
        });
      });
    }
  };

}(jQuery, Drupal, drupalSettings));
