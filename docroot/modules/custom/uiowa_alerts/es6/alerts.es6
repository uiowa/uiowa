// Polls the alerts feed on a fixed interval and updates the alerts block
// in place.
Drupal.behaviors.uiowaAlerts = {
  attach: function (context, settings) {
    once('uiowaAlertsGetAlerts', '.block-uiowa-alerts-block', context).forEach(el => {
      const au = new Drupal.uiowaAlerts.AlertsUtilities(el);

      // Initial fetch on page load; subsequent fetches are timer-driven.
      au.updateAlerts();

      // 60s cadence balances responsiveness against load on the alerts
      // endpoint.
      setInterval(() => {
        au.updateAlerts();
      }, 60000);
    });
  }
};
