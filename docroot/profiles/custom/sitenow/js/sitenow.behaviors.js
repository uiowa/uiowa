/**
 * @file
 * Sitenow global scripts. Attached to every page.
 */

(function ($, Drupal) {
    Drupal.behaviors.sitenow = {
      attach: function (context, setting) {
        $(document, context).once('sitenow').each(function () {
          console.log(
            'This is a Sitenow',
            setting.sitenow.version,
            'site.',
            'For more information, please visit https://sitenow.uiowa.edu.'
          );
        });
      }
    };
  })(jQuery, Drupal);
