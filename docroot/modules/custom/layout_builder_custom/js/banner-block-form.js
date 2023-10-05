(function ($, Drupal, once) {

  "use strict";

  Drupal.behaviors.bannerVideoExtend = {
    attach: function (context) {
      // We target the .media-library-widget class because there are not a lot of
      // good choices for classes/IDs to target in the inline block form.
      $(once('media-form-attach', '.media-library-widget', context)).each(function () {

        // Check that we can access the next field.
        const checkbox_wrapper = $('div[data-drupal-selector$="autoplay-wrapper"]');

        if (checkbox_wrapper.length) {
          // Check if the referenced media is a video.
          const mediaTypeVideo = this.querySelector('.media--video');

          if (mediaTypeVideo) {
            // Show the autoplay field.
            checkbox_wrapper.removeClass('js-hide');
            checkbox_wrapper.removeAttr('tabindex');
            checkbox_wrapper.removeAttr('aria-hidden');
          } else {
            // Hide the autoplay field.
            checkbox_wrapper.addClass('js-hide');
            checkbox_wrapper.tabIndex = -1;
            checkbox_wrapper.attr('aria-hidden', 'true');
          }
        }
      });
    }
  };

})(jQuery, Drupal, once);
