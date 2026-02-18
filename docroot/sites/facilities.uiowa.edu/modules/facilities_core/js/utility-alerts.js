(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.utilityAlerts = {
    attach: function (context) {
      once('utility-alerts', '.utility-alerts-container', context).forEach(function (container) {
        var url = drupalSettings.facilities_core.utilityAlertsUrl;
        var headingSize = drupalSettings.facilities_core.headingSize || 'h2';

        fetch(url + '?heading_size=' + encodeURIComponent(headingSize))
          .then(function (response) {
            if (!response.ok) {
              throw new Error('Unable to load utility alerts. Please try again later.');
            }
            return response.text();
          })
          .then(function (html) {
            container.innerHTML = html;
            Drupal.announce(Drupal.t('Utility alerts have been loaded.'));
          })
          .catch(function () {
            Drupal.announce(Drupal.t('Unable to load utility alerts.'));
            container.innerHTML = '<p>Unable to load utility alerts.</p>';
          });
      });
    },
  };
})(Drupal, drupalSettings, once);
