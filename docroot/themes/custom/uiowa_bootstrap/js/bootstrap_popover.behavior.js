/**
 * @file
 * Attach behaviors so that bootstrap popovers will work.
 */

(function ($) {
  // Bootstrap popover.
  Drupal.behaviors.bootstrap_popover = {
    attach: function(context, setting) {
      if ($.fn.popover) {
        $("[data-toggle='popover']").popover();
      }
    }
  };
})(jQuery);
