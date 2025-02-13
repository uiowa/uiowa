(function ($, Drupal, once) {

  "use strict";

  Drupal.behaviors.bannerVideoExtend = {
    attach: function (context) {
      // We target the .media-library-widget class because there are not a lot of
      // good choices for classes/IDs to target in the inline block form.
      $(once('media-form-attach', '.media-library-widget', context)).each(function () {

        // Check that we can access the next field.
        const checkbox_wrapper = context.querySelector('div[data-drupal-selector$="autoplay-wrapper"]');
        if (checkbox_wrapper) {
          // Check if the referenced media is a video.
          const mediaTypeVideo = context.querySelector('.media--video')
          //
          if (mediaTypeVideo) {
            // Show the autoplay field.
            checkbox_wrapper.classList.remove('js-hide');
            checkbox_wrapper.removeAttribute('tabindex');
            checkbox_wrapper.removeAttribute('aria-hidden');
          } else {
            // Hide the autoplay field.
            checkbox_wrapper.classList.add('js-hide');
            checkbox_wrapper.tabIndex = -1;
            checkbox_wrapper.setAttribute('aria-hidden', 'true');
          }
        }
      });
    }
  };

})(jQuery, Drupal, once);
