/**
 * @file
 * Chosen.
 */

(function (Drupal, once) {
  Drupal.behaviors.selectChosen = {
    attach: function (context) {
      once('selectChosen', "select[multiple='multiple']", context).forEach(function (select) {
        select.chosen({
          placeholder_text_multiple: "- Select -",
          width: "100%",
        });
      });
    }
  };
})(Drupal, once);
