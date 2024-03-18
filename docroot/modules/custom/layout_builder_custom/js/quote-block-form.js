(function ($, Drupal, once) {

  "use strict";

  Drupal.behaviors.quoteBlock = {
    attach: function (context) {
      // We target the .media-library-widget class because there are not a lot of
      // good choices for classes/IDs to target in the inline block form.
      $(once('media-form-attach', '.media-library-widget', context)).each(function () {

        // Check that we can access the next field.
        const $form_wrapper = $(this).parent().parent().parent();
        const $image_position_field = $form_wrapper.find('.form-item-layout-builder-style-card-media-position').find('select');

        const quoteImage = this.querySelector('.field--name-thumbnail');
        if (quoteImage) {
          // show image settings.
          $image_position_field.parent().show();

          } else {
              // hide image settings.
            $image_position_field.parent().hide();
          }
        });
      }
  };

})(jQuery, Drupal, once);
