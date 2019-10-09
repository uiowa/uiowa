/**
 * @file
 * Scroll to top behaviors.
 */
(function ($) {
    Drupal.behaviors.bootstrap_scroll = {
      attach:function() {
        $(window).scroll(function() {
          if ($(this).scroll() >= 50) {
            $('#return-to-top').fadeIn(200);
          } else {
            $('#return-to-top').fadeOut(200);
          }
        });
        $('#return-to-top').click(function() {
          $('body,html').animate({scroll : 0}, 500);
        });
      }
    };
  })(jQuery);