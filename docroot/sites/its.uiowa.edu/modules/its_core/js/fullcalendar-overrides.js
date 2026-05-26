(function (Drupal, once) {
  const MOBILE_BREAKPOINT = 768;

  function pickView(width) {
    return width <= MOBILE_BREAKPOINT ? 'listMonth' : 'dayGridMonth';
  }

  Drupal.behaviors.itsFullcalendarOverrides = {
    attach: function (context) {
      once('its-fullcalendar-overrides', '.fc', context).forEach(function (root) {
        // Icon span is decorative; button already has the accessible name.
        const fix = () => root.querySelectorAll('.fc-icon[role="img"]')
          .forEach(el => el.setAttribute('role', 'presentation'));
        fix();
        // Re-apply on calendar re-render.
        new MutationObserver(fix).observe(root, { childList: true, subtree: true });

        // Switch to list view on mobile; switch back when crossing back over.
        const calendar = root._fcCalendar;
        if (!calendar) return;

        let bucket = window.innerWidth <= MOBILE_BREAKPOINT ? 'mobile' : 'desktop';
        const initial = pickView(window.innerWidth);
        if (calendar.view.type !== initial) calendar.changeView(initial);

        let timer;
        window.addEventListener('resize', () => {
          clearTimeout(timer);
          timer = setTimeout(() => {
            const next = window.innerWidth <= MOBILE_BREAKPOINT ? 'mobile' : 'desktop';
            if (next !== bucket) {
              bucket = next;
              calendar.changeView(pickView(window.innerWidth));
            }
          }, 250);
        });
      });
    }
  };
})(Drupal, once);
