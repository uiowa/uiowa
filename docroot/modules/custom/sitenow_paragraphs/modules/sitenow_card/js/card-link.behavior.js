/**
 * @file
 * Link card.
 */

(function ($, Drupal) {
  Drupal.behaviors.cardLink = {
    attach: function (context, setting) {
      $('.card[data-href]', context).once('cardLink').each(function () {
        $('.card[data-href]')
            .on("keypress click", function (e) {
                if (e.which === 13 || e.type === 'click') {
                  if (e.target.tagName !== 'A') {
                    window.location = $(this).attr('data-href');
                  }
                }
            })
            .hover(function () {
              $(this).css("background-color", "#EDECEB");
            }, function () {
              $(this).css("background-color", "");
            })
      });
    }
  };
})(jQuery, Drupal);
