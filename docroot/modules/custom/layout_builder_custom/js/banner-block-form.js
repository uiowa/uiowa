(function ($, Drupal, once) {

  'use strict';

  // Behaviors for banner video and background options.
  Drupal.behaviors.bannerBlock = {
    attach: function (context, settings) {
      // Video autoplay handling
      // We target the .media-library-widget class because there are not a lot of
      // good choices for classes/IDs to target in the inline block form.
      $(once('media-form-attach', '.media-library-widget', context)).each(function () {
        // Check that we can access the next field.
        const checkbox_wrapper = context.querySelector('div[data-drupal-selector$="autoplay-wrapper"]');
        if (checkbox_wrapper) {
          // Check if the referenced media is a video.
          const mediaTypeVideo = context.querySelector('.media--video');

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

      // Background options handling.
      // Target the background options select.
      $(once('background-options-handler', 'select[name="settings[block_form][background_options]"]', context)).each(function () {
        const $background_options = $(this);
        const $media_overlay = $('select[name="layout_builder_style_media_overlay"]', context);
        const $overlay_checkbox = $('input[name^="layout_builder_style_banner_gradient"]', context);

        // Handle changes in the background options.
        function handleBackgroundChange() {
          if ($background_options.val() !== 'image') {
            // Clear the overlay dropdown.
            $media_overlay.val('');
            // Uncheck all gradient checkboxes.
            $overlay_checkbox.prop('checked', false);
          }
        }

        $background_options.change(handleBackgroundChange);
        handleBackgroundChange();
      });
    },
  };

})(jQuery, Drupal, once);
