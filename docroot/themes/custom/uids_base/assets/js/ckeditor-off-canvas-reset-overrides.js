(function () {
  // Override and remove CKEditor off-canvas list-item CSS rules.
  Drupal.behaviors.overrideCkEditorOffCanvasCss = {
    attach: function() {
      const checkInterval = setInterval(function() {
        // Look for the inline CSS CKEditor 5 ID.
        const offCanvasStylesheet = document.getElementById('ckeditor5-off-canvas-reset');

        if (!offCanvasStylesheet) {
          return;
        }

        const sheet = offCanvasStylesheet.sheet;
        // Ensure the CSS has loaded and contains CSS rules.
        if (sheet && sheet.cssRules) {
          clearInterval(checkInterval);
          // Loop through CSS rules.
          for (let ruleIndex = sheet.cssRules.length - 1; ruleIndex >= 0; ruleIndex--) {
            const rule = sheet.cssRules[ruleIndex];
            const ruleText = rule.cssText;
            // Check if the rule contains "list-item" or "decimal" text and remove them.
            if (/list-item|decimal/.test(ruleText)) {
              sheet.deleteRule(ruleIndex);
            }
          }
        }
      }, 50);
    }
  };
})();
