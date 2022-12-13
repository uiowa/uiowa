(function ($, Drupal) {
  // Attach get_gclid behavior to webform.
  Drupal.behaviors.get_gclid = {
    attach: function (context, settings) {
      $('.webform-submission-form', context).once('get_gclid').each(function (index) {
        const params = new URLSearchParams(window.location.search);
        const gclid = params.get("gclid");
        // If input is not already filled and query parameter exists, fill input.
        if (!document.querySelector('[name="gclid"]').value && gclid) {
          document.querySelector('[name="gclid"]').value = gclid;
        }
      });
    }
  };
})(jQuery, Drupal);
