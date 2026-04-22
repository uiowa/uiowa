(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.activeAlerts = {
    attach: function (context, settings) {
      once('active-alerts', '.active-alerts-container', context).forEach(function (container) {
        updateAlerts();

        // Check for changes every 55 seconds.
        setInterval(() => updateAlerts(), 10000);
        let debugcounter = 0;
        function updateAlerts() {
          const url = drupalSettings.emergency_core.activeAlertsUrl;
          const headingSize = drupalSettings.emergency_core.headingSize || 'h2';

          // const url = 'https://emergency.stage.drupal.uiowa.edu/api/active';
          fetch(url + '?heading_size=' + encodeURIComponent(headingSize))
            .then(function (response) {
              debugcounter++;
              if (!response.ok) {
                throw new Error('Unable to load active alerts. Please try again later.');
              }
              return response.text();
            })
            .then(function (json) {
              let html = '';
              const response = JSON.parse(json);
              if (response.data.length > 0) {
                response.data.forEach((item) => {
                  const alert_content = AlertsUtilities.hawkAlertContent(item);
                  const alert_markup = `
                  <div class="alert alert--danger alert--icon">
                    <div class="alert__icon">
                      <span class="fa-stack fa-1x">
                        <span role="presentation" class="fas fa-circle fa-stack-2x"></span>
<!--                        <span-->
<!--                          role="presentation"-->
<!--                          class="fas fa-stack-1x fa-inverse fa-exclamation"-->
<!--                        ></span>-->
                        <span style="position: relative;display: flex;width: 100%;height: 100%;z-index: 500;justify-content: center;align-items: center;font-weight: bold;color: white;">${debugcounter}</span>
                      </span>
                    </div>
                    ${alert_content}
                  </div>`;
                  html += alert_markup;
                });
              }
              else {
                html = `
                <div class="alert alert--success alert--icon">
                  <div class="alert__icon">
                    <span class="fa-stack fa-1x">
                      <svg role="presentation" class="svg-inline--fa fa-circle fa-stack-2x" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="circle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg=""><path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512z"></path></svg><!-- <span role="presentation" class="fas fa-circle fa-stack-2x"></span> Font Awesome fontawesome.com -->
<!--                      <svg role="presentation" class="svg-inline&#45;&#45;fa fa-check fa-inverse fa-stack-1x" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" data-fa-i2svg=""><path fill="currentColor" d="M438.6 105.4c12.5 12.5 12.5 32.8 0 45.3l-256 256c-12.5 12.5-32.8 12.5-45.3 0l-128-128c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0L160 338.7 393.4 105.4c12.5-12.5 32.8-12.5 45.3 0z"></path></svg>&lt;!&ndash; <span role="presentation" class="fas fa-stack-1x fa-inverse fa-check"></span> Font Awesome fontawesome.com &ndash;&gt;-->
                      <span style="position: relative;display: flex;width: 100%;height: 100%;z-index: 500;justify-content: center;align-items: center;font-weight: bold;color: white;">${debugcounter}</span>
                    </span>
                  </div>
                  <div>
                    <h2 class="headline">Campus Status Normal</h2>
                    <p><strong>There are no known emergencies at this time. The campus is operating normally.</strong></p><p>In the event of an emergency, this space will be used to provide timely information to the University community. It will be updated regularly as new information becomes available.</p>
                  </div>
                </div>`;
              }

              container.innerHTML = html;
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
