/**
 * @file
 */
(function ($, Drupal, drupalSettings, cookies, once) {
  let handle;
  let mainContent;
  let offCanvas;
  let body;
  let interacting = false;

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
          // This gets the proper elements for the drag handle fix.
          body = document.querySelector('body');
          handle = document.querySelector('.ui-resizable-handle.ui-resizable-w');
          mainContent = document.querySelector('.dialog-off-canvas-main-canvas');
          offCanvas = handle.parentElement;

          let justCreated = true;

          if (Drupal.offCanvas.isOffCanvas($element) && $element.find('.layout-selection').length === 1) {
            let offCanvasWidth;
            const offCanvasCookie = cookies.get('ui_off_canvas_width');
            if (offCanvasCookie === undefined) {
              offCanvasWidth = adjustedWidth(500)
            } else {
              offCanvasWidth = adjustedWidth(offCanvasCookie);
            }

            body.style.setProperty('--off-canvas-width', offCanvasWidth + 'px');

            let eventData = { settings: settings, $element: $element, offCanvasDialog: Drupal.offCanvas };
            $element.parent().on('dialogContentResize.off-canvas', eventData, function() {
              if (!justCreated) {
                // Cookie that expires in 99 years.
                const width = offCanvas.getBoundingClientRect().width
                cookies.set('ui_off_canvas_width', width, { expires: 36135, path: '/', domain: 'drupalSettings.layoutBuilderCustom.cookieDomain' });
              }
              justCreated = false;
            });
          }
          dragHandleBehaviorStopgap(true);

          handle.addEventListener('mousedown', function(event) {
            dragHandleBehaviorStopgapAwait(event);
          });
        }
      });
    }
  };

  // Wait for the mouse-up and reset the width of the main content.
  function dragHandleBehaviorStopgapAwait(event) {
    if (interacting) {
      return
    }
    interacting = true;
    document.addEventListener('mousemove', function(event) {
      dragHandleBehaviorStopgap();
    });
    handle.addEventListener('mouseup', function(event) {
      dragHandleResetEvents(event);
    });
  }
  function dragHandleBehaviorStopgap(init = false) {
    if (init) {
      body.style.setProperty('--off-canvas-width', adjustedWidth(parseFloat(offCanvas.getBoundingClientRect().width)) + 'px');
      offCanvas.style.width = adjustedWidth(parseFloat(offCanvas.getBoundingClientRect().width)) + 'px';
    }
    else {
      body.style.setProperty('--off-canvas-width', adjustedWidth(parseFloat(offCanvas.style.width)) + 'px');
    }
  }

  function dragHandleResetEvents(event) {

    document.removeEventListener('mousemove', function(event) {
      dragHandleBehaviorStopgap();
    });
    handle.removeEventListener('mouseup', function(event) {
      dragHandleResetEvents(event);
    });
    interacting = false;
  }

  function minmax(min, val, max) {
    if (val < min) {
      return min;
    } else if (val > max) {
      return max;
    } else {
      return val;
    }
  }

  function adjustedWidth(width) {
    return minmax(300, width, maxOffWidth());
  }

  function maxOffWidth() {
    let innerWidth;
    if (body) {
      innerWidth = body.getBoundingClientRect().width + 2;
    }
    else {
      innerWidth = window.innerWidth;
    }
    return innerWidth - handle.getBoundingClientRect().width;
  }

})(jQuery, Drupal, drupalSettings, window.Cookies, once);
