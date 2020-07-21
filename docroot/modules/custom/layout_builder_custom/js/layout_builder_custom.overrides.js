/**
 * @file
 */
(function ($, Drupal) {
    Drupal.behaviors.layout_builder_custom_overrides = {
        attach: function (context, settings) {
            $('.node-page-layout-builder-form', context).once('layout_builder_custom_overrides').each(function () {
              $(document).ajaxComplete(function (event, xhr, settings) {
                if (event.delegateTarget.visibilityState === 'visible' && settings.url.includes('section') === false) {
                  $(context)
                    .find('.ui-dialog-off-canvas')
                    .each(function () {
                      $(this).css('width', '500px');
                      $(this).css('left', '925px');
                    });
                }
              });
            });
        }
    };
})(jQuery, Drupal);
