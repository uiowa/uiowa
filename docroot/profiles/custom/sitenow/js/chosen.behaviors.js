/**
 * @file
 * Chosen.
 */

(function ($, Drupal, once) {
  Drupal.behaviors.selectChosen = {
    attach: function (context) {
      $(once('selectChosen', "select[multiple='multiple']", context)).each(function () {
        $("select[multiple='multiple']").chosen({
          placeholder_text_multiple: "- Select -",
          width: "100%",
        });
      });
    }
  };
})(jQuery, Drupal, once);
