(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.activeAlerts = {
    attach: function (context) {
      once('active-alerts', '.active-alerts-container', context).forEach(function (container) {
        var url = drupalSettings.emergency_core.activeAlertsUrl;
        console.log(drupalSettings.emergency_core.activeAlertsUrl);
        var headingSize = drupalSettings.emergency_core.headingSize || 'h2';

        fetch(url + '?heading_size=' + encodeURIComponent(headingSize))
          .then(function (response) {
            if (!response.ok) {
              throw new Error('Unable to load active alerts. Please try again later.');
            }
            return response.text();
          })
          .then(function (html) {
            container.innerHTML = html;
            Drupal.announce(Drupal.t('Active alerts have been loaded.'));
          })
          .catch(function () {
            Drupal.announce(Drupal.t('Unable to load active alerts.'));
            container.innerHTML = '<p>Unable to load active alerts.</p>';
          });
      });
    },
  };
})(Drupal, drupalSettings, once);
