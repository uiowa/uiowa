/**
 * @file
 * Renders the pickadate date picker.
 */

(function ($) {
  Drupal.behaviors.uiowaHoursDatePicker = {
    attach: function (context, settings) {
      $('#uiowa-hours-location-form', context).once('uiowaHoursDatePicker', function() {
        var locationCleanedName = settings.uiowaHours.name;
        $('.uiowa-hours-datepicker').pickadate({
          min: true,
          max: 180,
          format: 'dddd, mmm dd, yyyy',
          onSet: function(event) {
            if (event.select) {
            }
          },
          onClose: function() {
            // Hack to prevent popup from opening after switching windows.
            $('#edit-date').blur();
            $('#uiowa-hours-' + locationCleanedName + '-dates').focus();
          }
        });
      });
    }
  };
})(jQuery);
