/**
 * @file
 */
(function ($, Drupal) {
    Drupal.behaviors.layoutBuilderCustomOverrides = {
        attach: function (context, settings) {
          $(window).once('off-canvas-overrides').on({
            'dialog:aftercreate': function (event, dialog, $element) {
              let justCreated = true;
              if (Drupal.offCanvas.isOffCanvas($element) && $element.find('.layout-selection').length === 0) {
                let offCanvasWidth;
                const offCanvasCookie = $.cookie('ui_off_canvas_width');
                if (offCanvasCookie === undefined) {
                  offCanvasWidth = 500;
                } else {
                  offCanvasWidth = offCanvasCookie;
                }
                const container = $($element).parent()[0];
                container.style.position = 'fixed';
                container.style.width = offCanvasWidth + 'px';
                container.style.right = '0';
                container.style.left = 'auto';

                let eventData = { settings: settings, $element: $element, offCanvasDialog: Drupal.offCanvas };
                const $container = Drupal.offCanvas.getContainer($element);
                $element.on('dialogContentResize.off-canvas', eventData, function() {
                  if (!justCreated) {
                    // Cookie that expires in 99 years.
                    const width = $container.outerWidth();
                    $.cookie('ui_off_canvas_width', width, { expires: 36135, path: '/', domain: '.uiowa.edu'});
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

  // function mergeStyles(styles1, styles2){
  //   const styles1array = styles1.split('; ');
  //   let styles1map = {};
  //   styles1array.forEach(function (value) {
  //     const keyValue = value.split(': ');
  //     styles1map[keyValue[0].replace(';', '')] = keyValue[1].replace(';', '');
  //   })
  //
  //   const styles2array = styles2.split('; ');
  //   let styles2map = {};
  //   styles2array.forEach(function (value) {
  //     const keyValue = value.split(': ');
  //     styles2map[keyValue[0].replace('; ', '')] = keyValue[1].replace('; ', '');
  //   })
  //
  //   const styles2mapKeys = Object.keys(styles2map);
  //   styles2mapKeys.forEach(function (value) {
  //     styles1map[value] = styles2map[value];
  //   })
  //
  //   let stylestring = '';
  //
  //   styles2mapKeys.forEach(function (value) {
  //     styles1map[value] = styles2map[value];
  //   })
  // }

})(jQuery, Drupal);
