(function ($, Drupal) {
  Drupal.behaviors.galleryBlock = {
    attach: function (context, settings) {

      // Listen for changes to the condition that triggers visibility.
      $('.media-library-widget', context).once('media-form-attach').each(function () {
        const $form_wrapper = $(this).parent().parent().parent();
        const $image_format_field = $form_wrapper.find('select[name="layout_builder_style_media_format"]');
        const $image_masonry = $form_wrapper.find('select[name="layout_builder_style_default[]"]');

        // Function to handle changes in the image format field.
        function handleImageFormatChange() {
          if ($image_format_field.val() !== 'media_format_no_crop') {
            // Clear the selected values when the masonry field changes.
            $image_masonry.val([]);
          }
        }

        // Add change event handlers.
        $image_format_field.change(handleImageFormatChange);

        // Initialize the masonry field based on the initial image format value.
        handleImageFormatChange();
      });
    }
  };
})(jQuery, Drupal);
