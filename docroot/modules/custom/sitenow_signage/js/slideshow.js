/**
 * @file
 * JavaScript for the slideshow block.
 */

(function (Drupal) {
  Drupal.behaviors.signageSlideshow = {
    attach: function (context, settings) {
      context.querySelectorAll('.signage__slideshow').forEach(function (element) {
        // Maintain a list of dormant slides.
        drupalSettings.dormant = [];

        // Override prefers-reduced-motion for Splide.
        const originalMatchMedia = window.matchMedia;
        window.matchMedia = function (query) {
          if (query === '(prefers-reduced-motion: reduce)') {
            return {
              matches: false,
              media: query,
              onchange: null,
              addEventListener: () => {},
              removeEventListener: () => {},
              dispatchEvent: () => false,
            };
          }
          return originalMatchMedia(query);
        };

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

        const slides = splide.Components.Slides;

        // Check for publish times in reverse order.
        // We iterate through the array backwards,
        // So we don't goof the indices during the loop.
        const slideArray = [];

        slides.forEach((slide, index) => {
          slideArray.unshift(slide);
        });
        slideArray.forEach((slide, index) => {
          perSlide(slides, slide, index);
        });
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

      function perSlide(slides, slide, index) {
        const publish = slide.slide.querySelector('.field--name-field-slide-publish');
        const unpublish = slide.slide.querySelector('.field--name-field-slide-unpublish');
        const now = Date.now();


        console.log('__________________');
        console.log('Slide ' + slide.index + ' reporting!');
        console.log(slide);

        setSplideState(slides, slide, publish, unpublish);
        // console.log(slide);
        // console.log('Publish');
        //
        // console.log(publish);
        // console.log('Unpublish');
        // console.log(unpublish);
        //
      }

      function setSplideState(slides, slide, publish, unpublish) {
        const publishData = isPublished(publish, unpublish);
        console.log(publishData);

        // If the slide is unpublished...
        if (!publishData.published) {

          // Make it dormant and remove it from the slideshow.
          drupalSettings.dormant.push(slide);
          slides.remove(slide.index);
        }
      }

      function isPublished(publish, unpublish) {
        const now = Date.now();

        // Is there a published time?
        if (publish !== null ) {

          // Get the publish time.
          const datetimeEl = publish.querySelector('.datetime');
          const pubTime = new Date(datetimeEl.dateTime).getTime();

          // Are we before the publish time?
          if ( now < pubTime ) { // Yes? Unpublished with future action.
            console.log(pubTime);
            console.log(now);
            return new PublishData(false, pubTime - now);
          }
        }

        // Is there an unpublish time?
        if ( unpublish !== null) {

          // Get the unpublish time.
          const datetimeEl = unpublish.querySelector('.datetime');
          const unPubTime = new Date(datetimeEl.dateTime).getTime();

          // Are we after the unpublish time?
          if ( unPubTime <= now) { // Yes? Unpublished, no future action.
            return new PublishData(false);
          }
          else { // No? Published, with future action
            console.log(unPubTime);
            console.log(now);
            return new PublishData(true, unPubTime - now);
          }
        }

        // Default published, no future action
        return new PublishData(true);
      }

      function PublishData(state, futureAction = null) {
        this.published = state;
        this.futureAction = futureAction;
      }

      function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
      }

      async function awaitPublishChange(time) {
        console.log("Starting async operation.");
        await sleep(time); // Pause this async function for 2 seconds
        console.log("Async operation continued after 2 seconds.");
      }




























    },
  };
})(Drupal);
