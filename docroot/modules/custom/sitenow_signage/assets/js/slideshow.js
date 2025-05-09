/**
 * @file
 * JavaScript for the date and time block.
 */

(function ($, Drupal) {
  Drupal.behaviors.signageSlideshow = {
    attach: function (context, settings) {
      context.querySelectorAll('.signage-slideshow').forEach(function (element) {
        console.log('adding splide');
        // Initialize Splide with the settings.
        new Splide(element, {
          type: 'fade',
          pauseOnHover: false,
          drag: false,
          slideFocus: false,
          arrows: false,
          pagination: false,
          perPage: 1,
          autoScroll: {
            speed: 1,
          }
        }).mount();
      })
    },
  }
})(jQuery, Drupal);
