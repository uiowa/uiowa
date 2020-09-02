/**
 * @file
 */

(function ($, Drupal) {
  Drupal.theme.date_repeats_anchor = function (id, path, title, classes, icon) {
    var markup = '<span class="date-repeats-icon fas fa-ellipsis-h"></span><a id="' + id + '" href="' + path + '" title="' + title + '" class="' + classes + '">' + title + '</a>';
    return markup;
  };

  // Attach behavior.
  Drupal.behaviors.date_repeats = {
    attach: function (context, settings) {
      $('div.event-time', context).once("date-repeats").each(function () {
        var dateFuture = $(this).find('.date-instance__future');
        var datePast = $(this).find('.date-instance__past');
        var dateToggle = Drupal.theme('date_repeats_anchor', 'date-repeats-toggle', '#', 'Show All Dates', 'date-repeats-toggle is-collapsed');
        // Toggle attribute function.
        $.fn.toggleAttr = function (attr, attr1, attr2) {
          return this.each(function () {
            var self = $(this);
            if (self.attr(attr) == attr1) {
              self.attr(attr, attr2);
            }
            else {
              self.attr(attr, attr1);
            }
          });
        };

        // Toggle text function.
        $.fn.toggleText = function (text1, text2) {
          return this.each(function () {
            var self = $(this);
            if (self.text() == text1) {
              self.text(text2);
            }
            else {
              self.text(text1);
            }
          });
        };

        // Only run if there are future dates
        // we may not need this anymore.
        if (dateFuture.length > 0 || datePast.length > 0) {
          // Add date_toggle btn.
          $("div.event-time").append(dateToggle);

          // Set default states.
          dateFuture.hide();
          datePast.hide();

          // Click handler for toggle.
          $('#date-repeats-toggle').click(function (evt) {
            evt.preventDefault();

            $(this).toggleClass("is-collapsed is-expanded").toggleAttr('title', 'Show All Dates', 'Hide Dates').toggleText('Show All Dates', 'Hide Dates');

            // Toggle dates.
            dateFuture.slideToggle('fast');
            datePast.slideToggle('fast');
          });
        }
        else {
          $('div.date-repeats-rule').hide();
        }
      });
    }
  };
})(jQuery, Drupal);
