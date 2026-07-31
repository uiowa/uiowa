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
      // #9989: keep tray editors' viewport offsets corrected whenever Drupal
      // displace recalculates. Deferred by a tick so this always lands after
      // core's own drupalViewportOffsetChange handler, whatever the jQuery
      // binding order happens to be.
      $(document).on('drupalViewportOffsetChange.layoutBuilderCustomOverrides', function () {
        setTimeout(keepTrayEditorsInViewport, 0);
      });

      $(window).on({
        'dialog:aftercreate': function (event, dialog, $element) {
          // This gets the proper elements for the drag handle fix.
          body = document.querySelector('body');
          handle = document.querySelector('.ui-resizable-handle.ui-resizable-w');
          mainContent = document.querySelector('.dialog-off-canvas-main-canvas');
          offCanvas = handle.parentElement;

          let justCreated = true;

          if (Drupal.offCanvas.isOffCanvas($element)) {
            // #9989: Set default width via jQuery UI dialog option (inline
            // style) rather than CSS variable + !important. The CSS
            // !important approach caused CKEditor 5 toolbar to squish to
            // 260px. Using jQuery UI's dialog('option','width') sets a plain
            // inline style that CKEditor reads directly, with no CSS variable
            // indirection or !important timing issues.
            // Cookie persistence also applied so the user's last width is
            // restored on subsequent opens.
            const offCanvasCookie = cookies.get('ui_off_canvas_width');
            const offCanvasWidth = (offCanvasCookie !== undefined)
              ? adjustedWidth(parseFloat(offCanvasCookie))
              : adjustedWidth(600);
            $element.dialog('option', 'width', offCanvasWidth);

            // #9989: the editors in the tray are created after this fires, so
            // correct them once they exist.
            setTimeout(keepTrayEditorsInViewport, 0);

            let eventData = { settings: settings, $element: $element, offCanvasDialog: Drupal.offCanvas };
            $element.parent().on('dialogContentResize.off-canvas', eventData, function() {
              if (!justCreated) {
                // Cookie that expires in 99 years.
                const width = offCanvas.getBoundingClientRect().width;
                cookies.set('ui_off_canvas_width', width, { expires: 36135, path: '/', domain: drupalSettings.layoutBuilderCustom.cookieDomain });
              }
              justCreated = false;
            });
          }
        }
      });
    }
  };

  /**
   * Stop tray editors from treating the tray itself as a viewport obstruction.
   *
   * #9989. Core pipes Drupal.displace offsets straight into
   * editor.ui.viewportOffset for every CKEditor 5 instance, regardless of where
   * that instance lives:
   *
   * @see core/modules/ckeditor5/js/ckeditor5.js, drupalViewportOffsetChange
   *
   * The off-canvas tray registers a right-edge displacement equal to its own
   * width, so editors *inside* the tray are handed a viewport rect that excludes
   * the tray they sit in. CKEditor's getOptimalPosition() then finds no
   * candidate balloon position with a non-zero viewport or limiter intersection,
   * returns null, and BalloonPanelView falls back to POSITION_OFF_SCREEN
   * (top/left: -99999px). That is why the Linkit and core table balloons render
   * off-screen until a drag desynchronises the offset from the real tray width.
   *
   * The tray is not an obstruction for its own content, so zero out the
   * horizontal and bottom offsets. The toolbar's top offset is a genuine
   * obstruction and is kept.
   */
  function keepTrayEditorsInViewport() {
    if (!Drupal.CKEditor5Instances) {
      return;
    }
    const offsets = Drupal.displace.offsets;
    Drupal.CKEditor5Instances.forEach(function (editor) {
      if (editor.sourceElement && editor.sourceElement.closest('#drupal-off-canvas')) {
        editor.ui.viewportOffset = {
          top: offsets.top,
          right: 0,
          bottom: 0,
          left: 0
        };
      }
    });
  }

  // Wait for the mouse-up and reset the width of the main content.
  function dragHandleBehaviorStopgapAwait(event) {
    if (interacting) {
      return;
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
