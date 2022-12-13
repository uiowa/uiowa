(function ($, Drupal) {
  // Attach get_fbclid behavior to webform.
  Drupal.behaviors.get_fbclid = {
    attach: function (context, settings) {
      $('.webform-submission-form', context).once('get_fbclid').each(function (index) {
        const params = new URLSearchParams(window.location.search);
        const fbclid = params.get("fbclid");
        // If input is not already filled and query parameter exists, fill input.
        if (!document.querySelector('[name="fbclid"]').value && fbclid) {
          document.querySelector('[name="fbclid"]').value = fbclid;
        }
      });
    }
  };
})(jQuery, Drupal);
