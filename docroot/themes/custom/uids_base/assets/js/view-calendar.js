(function (Drupal, once) {
  Drupal.behaviors.uidsViewCalendarA11y = {
    attach: function (context) {
      once('uids-view-calendar-a11y', '.fc', context).forEach(function (root) {
        // Icon span is decorative — button already has the accessible name.
        const fix = () => root.querySelectorAll('.fc-icon[role="img"]')
          .forEach(el => el.setAttribute('role', 'presentation'));
        fix();
        // Re-apply on calendar re-render.
        new MutationObserver(fix).observe(root, { childList: true, subtree: true });
      });
    }
  };
})(Drupal, once);
