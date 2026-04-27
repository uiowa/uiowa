Drupal.behaviors.uiowaAlerts = {
  attach: function (context, settings) {
    // We want to use Drupal.once() in this instance because we don't want
    // to set multiple timeouts.
    once('uiowaAlertsGetAlerts', '.block-uiowa-alerts-block', context).forEach(el => {
      const au = new AlertsUtilities(el);
      // Get alerts on page load.
      au.updateAlerts();

      // Check for changes every 60 seconds.
      setInterval(() => {
        au.updateAlerts();
      }, 60000);
    });
  }
};
