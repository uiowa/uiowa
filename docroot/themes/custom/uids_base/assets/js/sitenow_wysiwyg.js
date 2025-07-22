(function () {
  Drupal.behaviors.overrideCkEditorOffCanvasCss = {
    attach: function() {
      const checkInterval = setInterval(function() {
        const offCanvasStylesheet = document.getElementById('ckeditor5-off-canvas-reset');

        if (!offCanvasStylesheet) {
          return;
        }

        const sheet = offCanvasStylesheet.sheet;

        if (sheet && sheet.cssRules) {
          clearInterval(checkInterval);
          for (let ruleIndex = sheet.cssRules.length - 1; ruleIndex >= 0; ruleIndex--) {
            const rule = sheet.cssRules[ruleIndex];
            const ruleText = rule.cssText;
            if (/list-item|decimal/.test(ruleText)) {
              sheet.deleteRule(ruleIndex);
            }
          }
        }
      }, 50);
    }
  };
})();
