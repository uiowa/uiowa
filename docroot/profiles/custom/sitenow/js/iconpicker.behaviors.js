/**
 * @file
 * Chosen.
 */
(function ($, Drupal) {
  Drupal.behaviors.iconpicker = {
    attach: function (context) {
      $('.fa-iconpicker', context).once('iconpicker').each(function () {
        $(this).iconpicker({
          inputSearch: true
        });
      });
    }
  };
})(jQuery, Drupal);
