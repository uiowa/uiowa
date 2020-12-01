(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.bannerVideoExtend = {
    attach: function (context, settings) {
      // We target the .media-library-widget class because there are not a lot of
      // good choices for classes/IDs to target in the inline block form.
      $('.media-library-widget', context).once('media-form-attach').each(function () {

        // Check if the referenced media is a video.
        const mediaTypeVideo = this.querySelector('.media--video');

        // @todo Add a class to this field so we can target it more precisely.
        if (mediaTypeVideo) {
          // Show the autoplay field.
          $(this).parent().next('.js-hide.form-wrapper').removeClass('js-hide');
        } else {
          // Hide the autoplay field.
          $(this).parent().next('.form-wrapper').addClass('js-hide');
        }
      });
    }
  };

})(jQuery, Drupal);
