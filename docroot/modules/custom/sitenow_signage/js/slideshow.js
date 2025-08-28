/**
 * @file
 * JavaScript for the slideshow block.
 */

(function (Drupal) {
  Drupal.behaviors.signageSlideshow = {
    attach: function (context, settings) {
      context.querySelectorAll('.signage__slideshow').forEach(function (element) {
        // Initialize Splide with the settings.
        const splide = new Splide(element, {
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
        });

        splide.on('move', (newIndex, prevIndex) => {
          pauseVideo(splide.Components.Slides.getAt(prevIndex).slide);
          playVideo(splide.Components.Slides.getAt(newIndex).slide);
        });

        splide.mount();
      });

      function pauseVideo(slide) {
        const iframe = slide.querySelector('iframe.media-oembed-content');
        if (iframe) {
          iframe.dataset.originalSrc = iframe.src;
          iframe.src = 'about:blank';
        }
      }

      function playVideo(slide) {
        const iframe = slide.querySelector('iframe.media-oembed-content');
        if (iframe && iframe.dataset.originalSrc) {
          iframe.src = iframe.dataset.originalSrc;
        }
      }
    },
  };
})(Drupal);
