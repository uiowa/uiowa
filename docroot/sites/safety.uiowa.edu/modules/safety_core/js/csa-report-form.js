(function ($, Drupal) {
  Drupal.behaviors.setTimeInputType = {
    attach: function (context, settings) {
      $(".time-input", context).attr("type", "time");
    },
  };
})(jQuery, Drupal);
