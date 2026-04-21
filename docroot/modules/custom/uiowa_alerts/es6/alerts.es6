Drupal.behaviors.uiowaAlerts = {
  attach: function (context, settings) {
    // We want to use Drupal.once() in this instance because we don't want
    // to set multiple timeouts.
    once('uiowaAlertsGetAlerts', '.block-uiowa-alerts-block', context).forEach(el => {
      const au = new AlertsUtilities(el, settings);
      // Get alerts on page load.

      //________
      const headingSize = 'h2';

      const url = 'https://emergency.stage.drupal.uiowa.edu/api/active';
      const encodedUrl = url + '?heading_size=' + encodeURIComponent(headingSize);
      //________
      au.updateAlerts(encodedUrl);

      // Check for changes every 60 seconds.
      setInterval(() => au.updateAlerts(), 60000);
    });
  }
};
