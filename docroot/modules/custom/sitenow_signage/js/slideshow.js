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
        // Check if iframe exists to avoid null errors,
        // Checks if src exists to avoid storing undefined,
        // Checks if src isn't blank to avoid storing blank as original.
        const iframe = slide.querySelector('iframe.media-oembed-content');
        if (iframe && iframe.src && iframe.src !== 'about:blank') {
          // Save the current video URL to restore later.
          iframe.dataset.originalSrc = iframe.src;
          // Replace the video URL with 'about:blank' to stop the video from loading/playing and pauses it.
          iframe.src = 'about:blank';
        }
      }

      /**
       * If the slide is an oembed video, play it.
       */
      function playIfVideo(slide) {
        const iframe = slide.querySelector('iframe.media-oembed-content');
        // Check if iframe exists to avoid null errors,
        // Check that we have original URL to avoid restoring nothing,
        // Check if src is currently blank to avoid overwriting working video.
        if (iframe && iframe.dataset.originalSrc && iframe.src === 'about:blank') {
          // Restores the video URL to make the video load and start playing again.
          iframe.src = iframe.dataset.originalSrc;
        }
      }
    },
  };
})(Drupal);
