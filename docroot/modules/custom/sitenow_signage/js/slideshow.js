/**
 * @file
 * JavaScript for the slideshow block.
 */

(function (Drupal) {
  Drupal.behaviors.signageSlideshow = {
    attach: function (context, settings) {
      context.querySelectorAll('.signage__slideshow').forEach(function (element) {
        // Get the first slide interval from drupalSettings, fallback to 5000.
        const firstSlideInterval = settings.signageSlideshow?.firstSlideInterval || 5000;
        // Initialize Splide with the settings.
        const splide = new Splide(element, {
          autoplay: true,
          interval: firstSlideInterval,
          type: 'fade',
          pauseOnHover: false,
          drag: false,
          speed: 1500,
          slideFocus: false,
          arrows: false,
          pagination: false,
          rewind: true,
        });

        // Set up initial video states before mounting.
        splide.on('mounted', () => {
          const slides = splide.Components.Slides;
          const activeIndex = splide.index;

          // Pause all videos initially.
          slides.forEach((slide, index) => {
            if (index !== activeIndex) {
              pauseIfVideo(slide.slide);
            }
          });

          // Play the active slide video.
          playIfVideo(slides.getAt(activeIndex).slide);
        });

        splide.on('move', (newIndex, prevIndex) => {
          pauseIfVideo(splide.Components.Slides.getAt(prevIndex).slide);
          playIfVideo(splide.Components.Slides.getAt(newIndex).slide);
        });

        splide.mount();
      });

      /**
       * If a slide is an oembed video, pause it.
       */
      function pauseIfVideo(slide) {
        const iframe = slide.querySelector('iframe.media-oembed-content');
        if (iframe && iframe.src && iframe.src !== 'about:blank') {
          iframe.dataset.originalSrc = iframe.src;
          iframe.src = 'about:blank';
        }
      }

      /**
       * If the slide is an oembed video, play it.
       */
      function playIfVideo(slide) {
        const iframe = slide.querySelector('iframe.media-oembed-content');
        if (iframe && iframe.dataset.originalSrc && iframe.src === 'about:blank') {
          iframe.src = iframe.dataset.originalSrc;
        }
      }
    },
  };
})(Drupal);
