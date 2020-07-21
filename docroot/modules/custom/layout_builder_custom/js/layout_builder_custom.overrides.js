/**
 * @file
 */
(function ($, Drupal) {
    Drupal.behaviors.layout_builder_custom_overrides = {
        attach: function (context, settings) {
            $('.node-page-layout-builder-form', context).once('layout_builder_custom_overrides').each(function () {
              $(window).on('dialog:aftercreate', function (event, dialog, $element) {
                if (Drupal.offCanvas.isOffCanvas($element) && $element.find('.layout-selection').length == 0) {
                  $($element).parent().attr('style', 'position: fixed; height: 281px; width: 500px; top: 79px; left: 925px;');
                }
              });
            });
        }
    };
})(jQuery, Drupal);
