/**
 * @file
 */
(function ($, Drupal) {
    Drupal.behaviors.layoutBuilderCustomOverrides = {
        attach: function (context, settings) {
          $(window).once('off-canvas-overrides').on({
            'dialog:aftercreate': function (event, dialog, $element) {
              if (Drupal.offCanvas.isOffCanvas($element) && $element.find('.layout-selection').length === 0) {
                let offCanvasWidth;
                const offCanvasCookie = $.cookie('ui_off_canvas_width');
                if (offCanvasCookie === undefined) {
                  offCanvasWidth = 500;
                } else {
                  offCanvasWidth = offCanvasCookie;
                }
                $($element).parent().attr('style', `position: fixed; width: ${offCanvasWidth}px; right: 0; left: auto;`);

                let eventData = { settings: settings, $element: $element, offCanvasDialog: Drupal.offCanvas };
                const $container = Drupal.offCanvas.getContainer($element);
                $element.on('dialogContentResize.off-canvas', eventData, function() {
                  // Per request, create cookie that expires in 99 years.
                  const width = $container.outerWidth();
                  $.cookie('ui_off_canvas_width', width, { expires: 36135, path: '/' });
                });
              }
            }
          });
        }
    };
})(jQuery, Drupal);
