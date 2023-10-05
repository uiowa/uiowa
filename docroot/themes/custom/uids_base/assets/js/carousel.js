(function ($, Drupal, once) {
  Drupal.behaviors.carousel = {
    attach: function (context) {
      $(once('carousel', 'div[data-carousel="carousel"]', context)).each(function () {
        var fade = $(this).hasClass('carousel-fade');
        $('.field--name-field-carousel-item').slick({
          dots: true,
          fade: fade
        });
      });
    }
  };
})(jQuery, Drupal, once);
