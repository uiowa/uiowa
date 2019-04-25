/**
 * @file
 * Chosen.
 */
(function ($, Drupal) {
  Drupal.behaviors.selectChosen = {
    attach: function (context, setting) {
      $("select[multiple='multiple']", context).once('selectChosen').each(function () {
        $("select[multiple='multiple']").chosen({
          placeholder_text_multiple: "- Select -",
          width: "100%",
        });
      });
    }
  };
})(jQuery, Drupal);
