/**
 * @file
 * JavaScript behaviors for admin pages.
 */

(function ($) {
  $(document).ready(function () {

    function checkPatternLabel(option) {
      if (option === '0') {
        $('.pattern-label').attr("disabled", "disabled");
        $('.pattern-label').attr("readonly", "readonly");
      }
      else {
        $('.pattern-label').removeAttr("disabled");
        $('.pattern-label').removeAttr("readonly");
      }
    }

    var option = $('input[name=node_type_page_status]:checked', '#edit-node-type-page-status').val();

    checkPatternLabel(option);

    $('#edit-node-type-page-status input').on('change', function () {
      option = $('input[name=node_type_page_status]:checked', '#edit-node-type-page-status').val();
      checkPatternLabel(option);
    });

  });
})(jQuery);
