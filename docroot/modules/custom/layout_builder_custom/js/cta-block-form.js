(function ($, Drupal, once) {
  Drupal.behaviors.layoutBuilderBlock = {
    attach: function (context, settings) {
      const $alignment_field = $('select[name="layout_builder_style_alignment"]', context);

      if ($alignment_field.length > 0) {
        const $button_position_checkbox = $('input[name="layout_builder_style_button_width[button_position_right]"]', context);

        function handleAlignmentChange() {
          if ($alignment_field.val() !== 'block_alignment_left') {
            $button_position_checkbox.prop('checked', false);
          }
        }

        $(once('layout-builder-style', $alignment_field)).on('change', handleAlignmentChange);
        handleAlignmentChange();
      }
    }
  };
})(jQuery, Drupal, once);
