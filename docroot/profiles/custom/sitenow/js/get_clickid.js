(function ($, Drupal, drupalSettings, once) {
  // Attach get_clickid behavior to webform.
  Drupal.behaviors.get_clickid = {
    attach: function (context) {
      // With Acquia varnish, Webform can't pre-populate
      // Google/Facebook Click IDs (gclid, fbclid).
      // So this JS is added to forms with these hidden inputs.
      const params = new URLSearchParams(window.location.search);
      let query = [];
      // Get the params from the address bar, so we have values to
      // populate inputs that meet our attribute criteria.
      // Prepopulate is a custom attribute added in sitenow.profile sitenow_webform_element_alter().
      console.log(drupalSettings.sitenow.webformPrepopulateQueryKeys);
      drupalSettings.sitenow.webformPrepopulateQueryKeys.forEach(function (param) {
          if (params.get(param)) {
            if (context.querySelectorAll('input[prepopulate="true"][name="' + param + '"]').length) {
              query.push('input[prepopulate="true"][name="' + param + '"]');
            }
          }
        }
      )

      // If there are params matching our target elements, loop through and
      // populate the value.
      if (query.length) {
        let queryString = query.join(',');
        $(once('get_clickid', '.webform-submission-form', context)).each(function () {
          context.querySelectorAll(queryString).forEach(function (input) {
            input.value = params.get(input.name);
          });
        });
      }
    }
  };
})(jQuery, Drupal, drupalSettings, once);
