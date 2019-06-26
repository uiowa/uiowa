/**
 * @file
 * Lockup Preview.
 */
(function ($, Drupal) {
  Drupal.behaviors.lockupPreview = {
    attach: function(context, settings) {
      $('#lockup-preview').hide();
      if($("#edit-field-lockup-primary-unit-0-value").val() != ''){
        $('#lockup-preview').show();
        $('.lockup-stacked .primary-unit').text($("#edit-field-lockup-primary-unit-0-value").val());
        $('.lockup-horizontal .primary-unit').text($("#edit-field-lockup-primary-unit-0-value").val());
      }
      if($("#edit-field-lockup-sub-unit-0-value").val() != ''){
        $('#lockup-preview').show();
        $('.lockup-stacked .sub-unit').text($("#edit-field-lockup-sub-unit-0-value").val());
        $('.lockup-horizontal .sub-unit').text($("#edit-field-lockup-sub-unit-0-value").val());
      }
      $("#edit-field-lockup-primary-unit-0-value").on('input', function(event) {
        $('#lockup-preview').show();
        $('.lockup-stacked .primary-unit').text(event.target.value);
        $('.lockup-horizontal .primary-unit').text(event.target.value);
      });
      $("#edit-field-lockup-sub-unit-0-value").on('input', function(event) {
        $('#lockup-preview').show();
        $('.lockup-stacked .sub-unit').text(event.target.value);
        $('.lockup-horizontal .sub-unit').text(event.target.value);
      });
    }
  };
})(jQuery, Drupal);
