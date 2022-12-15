(function ($, Drupal) {
  // Attach get_clickid behavior to webform.
  Drupal.behaviors.get_clickid = {
    attach: function (context, settings) {
      // With Acquia varnish, Webform can't pre-populate
      // Google/Facebook Click IDs (gclid, fbclid).
      // So this JS is added to forms with these hidden inputs.
      const params = new URLSearchParams(window.location.search);
      let query = [];
      // Get the params from the address bar so we have values to
      // populate inputs that meet our attribute criteria.
      // Prepopulate is a custom attribute added in sitenow.profile sitenow_webform_element_alter().
      ['gclid', 'fbclid'].forEach(function (param) {
          if (params.get(param)) {
            if (context.querySelectorAll('input[prepopulate="true"][type="hidden"][name="' + param + '"]').length) {
              query.push('input[prepopulate="true"][type="hidden"][name="' + param + '"]');
              console.log(context.querySelectorAll('input[prepopulate="true"][type="hidden"][name="' + param + '"]').value);
            }
          }
        }
      )

      // If there are params matching our target elements, loop through and
      // populate the value.
      if (query.length) {
        let queryString = query.join(',');
        $('.webform-submission-form', context).once('get_clickid').each(function (index) {
          context.querySelectorAll(queryString).forEach(function (input) {
              input.value = params.get(input.name);
          })
        });
      }
    }
  };
})(jQuery, Drupal);
