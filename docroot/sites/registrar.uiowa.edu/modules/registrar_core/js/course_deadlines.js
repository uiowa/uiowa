(function($, Drupal) {
  Drupal.uiowaMauiCourseDeadlines = function() {
    $(document).ajaxComplete(function(event, XMLHttpRequest, ajaxOptions) {
      var response = $.parseJSON(XMLHttpRequest.responseText);

      if (response[0].settings.ajax) {
        var key = Object.keys(response[0].settings.ajax);
        var trigger = response[0].settings.ajax[key].submit._triggering_element_name;

        switch(trigger) {
          case 'department':
            $('.form-item-department select').val('');
            $('.form-item-course select').val('').attr('disabled', true);
            $('.form-item-section select').val('').attr('disabled', true);
            $('#uiowa-maui-course-deadlines').empty();
            break;

          case 'course':
            $('.form-item-course select').val('');
            $('.form-item-section select').val('').attr('disabled', true);
            $('#uiowa-maui-course-deadlines').empty();
            break;

          case 'section':
            $('.form-item-section select').val('');
            $('#uiowa-maui-course-deadlines').empty();
            break;
        }
      };
    });
  };

  // Attach uiowaMauiCourseDeadlines behavior.
  Drupal.behaviors.uiowaMauiCourseDeadlines = {
    attach: function(context, settings) {
      $(once('uiowaMauiCourseDeadlines', 'uiowa-maui-course-deadlines-form', context)).each(function() {
        Drupal.uiowaMauiCourseDeadlines();
      });
    }
  };

})(jQuery, Drupal);
