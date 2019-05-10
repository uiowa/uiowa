/**
 * @file
 * Attach behaviors so that bootstrap tooltips will work.
 */

(function ($) {
  // Bootstrap tooltip.
  Drupal.behaviors.bootstrap_tooltip = {
    attach: function(context, setting) {
      if ($.fn.tooltip) {
        $("[data-toggle='tooltip']").tooltip();
      }
    }
  };
})(jQuery);
