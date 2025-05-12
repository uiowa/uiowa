/**
 * @file
 * JavaScript for the date and time block.
 */

(function (Drupal) {
  Drupal.behaviors.signageSlideshow = {
    attach: function (context, settings) {
      context.querySelectorAll('.signage-slideshow').forEach(function (element) {
        console.log('adding splide', element);
        // Initialize Splide with the settings.
        new Splide(element, {
          autoplay: true,
          interval: 5000,
          type: 'fade',
          pauseOnHover: false,
          drag: false,
          speed: 1500,
          slideFocus: false,
          arrows: false,
          pagination: false,
          rewind: true,
        }).mount();
      })
    },
  }
})(Drupal);
