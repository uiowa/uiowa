/**
 * @file
 */
(function ($, Drupal, drupalSettings, cookies, once) {
  Drupal.behaviors.layoutBuilderCustomOverrides = {
    attach: function (context, settings) {
      // Can not use `window` or `document` directly.
      if (!once('off-canvas-overrides', 'html').length) {
        // Early return avoid changing the indentation
        // for the rest of the code.
        return;
      }
      $(window).on({
        'dialog:aftercreate': function (event, dialog, $element) {
          let justCreated = true;
          if (Drupal.offCanvas.isOffCanvas($element) && $element.find('.layout-selection').length === 0) {
            let offCanvasWidth;
            const offCanvasCookie = cookies.get('ui_off_canvas_width');
            if (offCanvasCookie === undefined) {
              offCanvasWidth = 500;
            } else {
              offCanvasWidth = offCanvasCookie;
            }
            const container = $($element).parent()[0];
            container.style.width = offCanvasWidth + 'px';

            let eventData = { settings: settings, $element: $element, offCanvasDialog: Drupal.offCanvas };
            const $container = Drupal.offCanvas.getContainer($element);
            $element.on('dialogContentResize.off-canvas', eventData, function() {
              if (!justCreated) {
                // Cookie that expires in 99 years.
                const width = $container.outerWidth();
                cookies.set('ui_off_canvas_width', width, { expires: 36135, path: '/', domain: drupalSettings.layoutBuilderCustom.cookieDomain });
              }
              justCreated = false;
            });
          }
        }
      });
    }
  };

  // Allows saving filtered and minimal html content while Source is open.
  // Solution from https://www.drupal.org/project/drupal/issues/3095304.
  var origBeforeSubmit = Drupal.Ajax.prototype.beforeSubmit;
  Drupal.Ajax.prototype.beforeSubmit = function (formValues, element, options) {
    if (typeof(CKEDITOR) !== 'undefined' && CKEDITOR.instances) {
      const instances = Object.values(CKEDITOR.instances);
      instances.forEach(editor => {
        formValues.forEach(formField => {
          // Get field name from the id in the editor so that it covers all
          // fields using ckeditor.
          let element = document.querySelector(`#${editor.name}`)
          if (element) {
            let fieldName = element.getAttribute('name');
            if (formField.name === fieldName && editor.mode === 'source') {
              formField.value = editor.getData();
            }
          }
        });
      });
    }
    origBeforeSubmit.call(formValues, element, options);
  };

})(jQuery, Drupal, drupalSettings, window.Cookies, once);
