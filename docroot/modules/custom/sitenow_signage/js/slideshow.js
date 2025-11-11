/**
 * @file
 * JavaScript for the slideshow block.
 */

(function (Drupal) {
  Drupal.behaviors.signageSlideshow = {
    attach: function (context, settings) {
      const debug = true;

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

      /**
       * Code to be run per slide.
       *
       * @param {slides} slides A Splide slides object, the slideshow.
       * @param {slide} slide A Splide slide object.
       **/
      function perSlide(slides, slide) {
        const publish = slide.slide.querySelector('.field--name-field-slide-publish');
        const unpublish = slide.slide.querySelector('.field--name-field-slide-unpublish');

        setSplideState(slides, slide, publish, unpublish);
      }

      /**
       * Sets a slide to be in the slideshow,or removes it.
       *
       * @param {slides} slides A Splide slides object, the slideshow.
       * @param {slide} slide A Splide slide object.
       * @param {HTMLElement} publish The rendered element output by drupal
       *     containing the time at which a slide should be published.
       * @param {HTMLElement} unpublish The rendered element output by drupal
       *     containing the time at which a slide should be unpublished.
       **/
      function setSplideState(slides, slide, publish, unpublish) {

        /*
          Gets a PublishData object, which holds information on:
            - If the slide should be published.
            - When the slide state should be checked again, if applicable.
         */
        const publishData = isPublished(publish, unpublish);

        if (debug) {
          console.log('__________________');
          let status = 'Setting slide ' + slide.index + ' to ';
          publishData.published ? status += 'published.' : status += 'unpublished.';
          console.log(status);
          console.log('__________________');
        }

        /*
          We hold the slide reference here because it could
            either be in the dormant array OR the slideshow.
         */
        let dynamicSlidePlacement = slide;

        if (!publishData.published) {

          // Make it dormant and remove it from the splide slideshow.
          drupalSettings.dormant.unshift(slide);
          dynamicSlidePlacement = drupalSettings.dormant[0];
          slides.remove(slide.index);
        }

        else if (publishData.published) {

          // If the slide is in the dormant array...
          const index =  drupalSettings.dormant.indexOf(slide);
          if (index > -1) {
            if (debug) {
              console.log('__________________');
              console.log('Adding slide to slideshow.');
              console.log(slide.slide, slide.index);
              console.log('__________________');
            }
            // Add slide to the splide slideshow
            slides.add(slide.slide, slide.index);

            // Remove it from being dormant.
            drupalSettings.dormant.splice(index, 1);
          }

        }

        // If we have future action, recur after delay.
        if (publishData.futureAction !== null) {
          if (debug) {
            console.log('__________________');
            console.log('Future action detected!');
            console.log('__________________');
          }
          awaitPublishChange(
            publishData.futureAction,
            () => {
              setSplideState(slides, dynamicSlidePlacement, publish, unpublish);
            }
          );
        }
      }

      /**
       * Operates on HTMLElements to determine whether a slide should be
       *  published or unpublished.
       *
       * @param {HTMLElement} publish The rendered element output by drupal
       *     containing the time at which a slide should be published.
       * @param {HTMLElement} unpublish The rendered element output by drupal
       *     containing the time at which a slide should be unpublished.
       * @return {PublishData} An object which holds information on:
       *     - If the slide should be published.
       *     - When the slide state should be checked again, if applicable.
      **/
      function isPublished(publish, unpublish) {
        const now = Date.now();

        // Is there a published time?
        if (publish !== null ) {

          // Get the publish time.
          const datetimeEl = publish.querySelector('.datetime');
          const pubTime = new Date(datetimeEl.dateTime).getTime();

          // Are we before the publish time?
          if ( now < pubTime ) { // Yes? Unpublished with future action.
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
            return new PublishData(true, unPubTime - now);
          }
        }

        // Default published, no future action
        return new PublishData(true);
      }

      /**
       * Represents data for the state of a slide.
       *
       *  We could probably store the dormant slides in here
       *    and remove the need for the dormant array.
       *
       * @typedef {object} PublishData
       * @property {boolean} state - Whether the entity should
       *  be published or not.
       * @property {number | null} futureAction - The number of milliseconds
       *  until the entity should be checked again.
       */
      function PublishData(state, futureAction = null) {
        this.published = state;
        this.futureAction = futureAction;
      }

      /**
       * Waits for `ms` milliseconds.
       *
       * @param {number} ms The number of milliseconds to wait.
       * @return {Promise}
       **/
      function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
      }

      /**
       * Awaits `time` + 1000 milliseconds, and then executes `callback`.
       *
       * @param {number} time milliseconds to wait until
       *  executing the callback.
       * @param {callback} callback The callback to execute.
       **/
      async function awaitPublishChange(time, callback) {
        if (debug) {
          console.log('__________________');
          console.log("Waiting for "  + ((time+1000)/1000) + ' seconds.');
          console.log('__________________');
        }
        const ms = time+1000; // Pause this async function 'time' + 1 seconds.
        await sleep(ms);
        callback();
      }
    },
  };
})(Drupal);
