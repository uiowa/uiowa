/**
 * @file
 * Custom scripts for theme.
 */

(function ($, Drupal) {
    Drupal.behaviors.tableResponsive = {
        attach: function (context, settings) {
            $('table', context).once('table-responsive-attach').each(function () {
                // If it doesn't already have a responsive wrapper.
                if (!$(this).closest(".table-responsive").length) {
                    $(this).wrap("<div class='table-responsive'></div>");
                }

              $(this).addClass('table-striped table-bordered');
              $('thead', this).addClass('thead-dark');
            });
        }
    };
})(jQuery, Drupal);
