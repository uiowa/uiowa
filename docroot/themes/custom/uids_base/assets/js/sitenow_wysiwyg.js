(function () {
  Drupal.behaviors.overrideCkEditorOffCanvasCss = {
    attach: () => {
      const i = setInterval(() => {
        const s = document.getElementById('ckeditor5-off-canvas-reset')?.sheet;
        if (s?.cssRules && (clearInterval(i), true)) {
          for (let j = s.cssRules.length; j--;) {
            /list-item|decimal/.test(s.cssRules[j].cssText) && s.deleteRule(j);
          }
        }
      }, 50);
    }
  };
})();
