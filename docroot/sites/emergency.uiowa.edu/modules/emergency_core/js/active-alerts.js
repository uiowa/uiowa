(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.activeAlerts = {
    attach: function (context, settings) {
      once('active-alerts', '.active-alerts-container', context).forEach(function (container) {
        updateAlerts();

        // Check for changes every 55 seconds.
        setInterval(() => updateAlerts(), 10000);
        let debugcounter = 0;
        function updateAlerts() {
          // const url = drupalSettings.emergency_core.activeAlertsUrl;
          const headingSize = drupalSettings.emergency_core.headingSize || 'h2';
          const url = 'https://emergency.uiowa.ddev.site/api/active';
          // const url = 'https://emergency.stage.drupal.uiowa.edu/api/active';
          container.innerHTML = '';
          AlertsUtilities.fetchAlerts(url)
            .then(function (response) {
              if (response.data.length > 0) {
                response.data.forEach(async (item) => {
                  const alert_content = AlertsUtilities.hawkAlertContent(item);
                  const full_hawk_alert_string = AlertsUtilities.fullHawkAlertMarkup(alert_content);
                  const hawk_alert_dom_elements = AlertsUtilities.createElementFromHTML(full_hawk_alert_string)
                  container.append(hawk_alert_dom_elements);

                  if (item?.relationships?.field_hawk_alert_situation?.data !== undefined) {
                    const update_data = item?.relationships?.field_hawk_alert_situation?.data;
                    if (update_data.length > 0) {
                      const hawk_alert_body = hawk_alert_dom_elements.querySelector('.hawk-alert-body.updates');
                      await AlertsUtilities.getSituationUpdates(item)
                        .then((response)=>{
                          hawk_alert_body.innerHTML += AlertsUtilities.hawkAlertSituationUpdateSectionTitle();
                          response.data.forEach((update) => {
                            hawk_alert_body.innerHTML += AlertsUtilities.hawkAlertStatusUpdateContent(update);
                          });
                        })
                    }
                  }
                });
              }
              else {
                container.innerHTML = `
                <div class="alert alert--success alert--icon">
                  <div class="alert__icon">
                    <span class="fa-stack fa-1x">
                      <svg role="presentation" class="svg-inline--fa fa-circle fa-stack-2x" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"></path></svg><!-- <span role="presentation" class="fas fa-circle fa-stack-2x"></span> Font Awesome fontawesome.com -->
                      <svg role="presentation" class="svg-inline--fa fa-check fa-inverse fa-stack-1x" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" data-fa-i2svg=""><path fill="currentColor" d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"></path></svg><!-- <span role="presentation" class="fas fa-stack-1x fa-inverse fa-check"></span> Font Awesome fontawesome.com -->
                    </span>
                  </div>
                  <div>
                    <h2 class="headline">Campus Status Normal</h2>
                    <p><strong>There are no known emergencies at this time. The campus is operating normally.</strong></p><p>In the event of an emergency, this space will be used to provide timely information to the University community. It will be updated regularly as new information becomes available.</p>
                  </div>
                </div>`;
              }
              Drupal.announce(Drupal.t('Active alerts have been loaded.'));

            })
            .catch(function () {
              container.innerHTML = '<p>Unable to load active alerts.</p>';
              Drupal.announce(Drupal.t('Unable to load active alerts.'));
            });
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
