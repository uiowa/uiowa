(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.bannerVideoExtend = {
    attach: function (context, settings) {
      // We target the .media-library-widget class because there are not a lot of
      // good choices for classes/IDs to target in the inline block form.
      $('.media-library-widget', context).once('media-form-attach').each(function () {

        // Check that we can access the next field.
        // @todo Add a class to this field widget wrapper so that we can target it more precisely.
        const $form_wrapper = $(this).parent().parent().parent();
        const $image_format_field = $form_wrapper.find('.form-item-layout-builder-style-media-format').find('select');
        const $image_size_field = $form_wrapper.find('.form-item-layout-builder-style-card-image-size').find('select');

        if ($image_format_field.length || $image_size_field.length) {
          $image_size_field.css({ 'background-color': 'red'});
          // $image_size_field.parent().css({'display': none});
          // Check if the referenced media is a video.
          const mediaTypeVideo = this.querySelector('.media--video');

          if (mediaTypeVideo) {
            // Set image format to widescreen.
            $image_format_field.val('media_format_widescreen').change();
            // Hide image format.
            $image_format_field.parent().hide();
            $image_size_field.parent().hide();


            // @ todo investigate alternative ways of hiding image format that does not affect image size state.
          } else {
              // Show image format.
            $image_format_field.parent().show();
            $image_size_field.parent().show();
          }
        }
      });
    }
  };

})(jQuery, Drupal);
