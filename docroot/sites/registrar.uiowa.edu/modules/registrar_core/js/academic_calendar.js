/**
 * @file
 * Academic calendar.
 */

(function($) {
  // Attach uiowaMauiAcademicCalendar behavior.
  Drupal.behaviors.uiowaMauiAcademicCalendar = {
    attach: function(context, settings) {
      $('.uiowa-maui-fullcalendar', context).once('uiowaMauiAcademicCalendar', function() {
        $(this).fullCalendar({
          nextDayThreshold: '00:00:00',
          eventStartEditable: false,
          displayEventTime: false,
          header: {
            left: 'today prev,next',
            center: 'title',
            right: 'month,listMonth'
          },
          events: Drupal.settings.uiowaMaui.calendarDates,
          eventRender: function (event, element, view) {
            element.attr('role', 'button')
              .attr('tabindex', 0)
              .removeAttr('href')
              .removeClass('fc-event');

              // Filter logic.
              var categories = $('#edit-category').val();
              var showSubsessions = $('#edit-subsession').is(':checked');

              if (categories == null) {
                if (showSubsessions === true) {
                  return true;
                } else {
                  return (showSubsessions === event.subSession);
                }
              }
              else {
                var match = false;

                categories.forEach(function(v) {
                  if (v in this.categories) {
                    if (showSubsessions === true) {
                      match = true;
                    }
                    else {
                      match = (showSubsessions === this.subSession);
                    }
                  }
                }, event);

                return match;
              }
          },
          eventClick: function(event, jsEvent, view) {
            if (jQuery.fn.popover) {
              $(this).popover({
                html: true,
                title: event.popoverTitle,
                content: event.popoverContent,
                placement: 'top',
                trigger: 'focus',
                container: 'body'
              }).popover('show');
            }

            return false;
          }
        });

        $('#edit-category').on('change', function() {
          $('.uiowa-maui-fullcalendar').fullCalendar('rerenderEvents');
        });

        $('#edit-subsession').on('change', function() {
          $('.uiowa-maui-fullcalendar').fullCalendar('rerenderEvents');
        });

        $('.fc-prev-button').click(function(e) {
          // getDate results in 1st of the month.
          var currentDate = $('.uiowa-maui-fullcalendar').fullCalendar('getDate');
          var sessionDate = moment(Drupal.settings.uiowaMaui.currentSession.startDate);

          if (currentDate.isBefore(sessionDate)) {
            $(this).attr('disabled', true);
          }
          else {
            $(this).attr('disabled', false);
          }
        });

        $('.fc-next-button').click(function(e) {
          // getDate results in last day of the previous month. Therefore, add
          // one month.
          var currentDate = $('.uiowa-maui-fullcalendar').fullCalendar('getDate');
          currentDate.add(1, 'month');

          var sessionDate = moment(Drupal.settings.uiowaMaui.lastSession.endDate);

          if (currentDate.isSameOrAfter(sessionDate)) {
            $(this).attr('disabled', true);
          }
          else {
            $(this).attr('disabled', false);
          }
        });
      });
    }
  };

})(jQuery);
