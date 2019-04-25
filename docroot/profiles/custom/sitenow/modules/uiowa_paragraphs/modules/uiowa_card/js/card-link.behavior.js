/**
 * @file
 * Link card.
 */
(function ($, Drupal) {
  Drupal.behaviors.cardLink = {
    attach: function (context, setting) {
      $('.card[data-href]', context).once('cardLink').each(function () {
        $('.card[data-href]').on("keypress click", function (e) {
          if (e.which === 13 || e.type === 'click') {
            window.location = $(this).attr('data-href');
          }
        });
      });
    }
  };
})(jQuery, Drupal);
