// Polls the alerts feed on a fixed interval and updates the alerts block
// in place.
Drupal.behaviors.uiowaAlerts = {
  attach: function (context, settings) {
    once('uiowaAlertsGetAlerts', '.block-uiowa-alerts-block', context).forEach(el => {
      const AlertsUtil = Drupal.uiowaAlerts.AlertsUtilities;
      const alertsBlock = new AlertsUtil(el);

      // Initial fetch on page load; subsequent fetches are timer-driven.
      AlertsUtil.whenDocumentLoaded(() => alertsBlock.updateAlerts());

      // 60s cadence balances responsiveness against load on the alerts
      // endpoint.
      setInterval(() => {
        alertsBlock.updateAlerts();
      }, 60000);
    });
  }
};
