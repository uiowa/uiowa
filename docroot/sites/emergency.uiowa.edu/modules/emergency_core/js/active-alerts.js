(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.activeAlerts = {
    attach: function (context, settings) {
      once('active-alerts', '.active-alerts-container', context).forEach(function (container) {
        updateActiveAlerts();

        // Check for changes every 55 seconds.
        setInterval(() => updateActiveAlerts(), 55000);

        async function updateActiveAlerts() {
          // Drop a loading text if one is currently showing.
          const loadingEl = container.querySelector('.loading');
          if (loadingEl) {
            loadingEl.remove();
          }

          try {
            const response = await AlertsUtilities.fetchAlerts();
            if (response.data.length > 0) {
              await syncAlerts(response.data);
            }
            else {
              renderCampusNormal();
            }
            Drupal.announce(Drupal.t('Active alerts have been loaded.'));
          }
          catch (e) {
            container.innerHTML = '<p>Unable to load active alerts.</p>';
            Drupal.announce(Drupal.t('Unable to load active alerts.'));
          }
        }

        async function syncAlerts(items) {
          // Drop a campus-normal block if one is currently showing.
          const normalEl = container.querySelector('.alert--success');
          if (normalEl) {
            normalEl.remove();
          }

          const existing = new Map();
          container.querySelectorAll('[data-alert-id]').forEach((el) => {
            existing.set(el.getAttribute('data-alert-id'), el);
          });

          const seen = new Set();
          for (const item of items) {
            const id = `hawk-alert-${item.attributes.date}`;
            seen.add(id);
            let alertEl = existing.get(id);
            if (!alertEl) {
              const markup = AlertsUtilities.fullHawkAlertMarkup(
                AlertsUtilities.hawkAlertContent(item)
              );
              alertEl = AlertsUtilities.createElementFromHTML(markup);
              alertEl.setAttribute('data-alert-id', id);
              container.append(alertEl);
            }
            await syncSituationUpdates(alertEl, item);
          }

          existing.forEach((el, id) => {
            if (!seen.has(id)) {
              el.remove();
            }
          });
        }

        async function syncSituationUpdates(alertEl, item) {
          const body = alertEl.querySelector('.hawk-alert-body.updates');
          if (!body) {
            return;
          }

          const updateData = item?.relationships?.field_hawk_alert_situation?.data;
          const hasUpdates = Array.isArray(updateData) && updateData.length > 0;
          const title = body.querySelector('.hawk-alert-updates-title');

          if (!hasUpdates) {
            // Clear any stale updates + title on the N -> 0 transition.
            body.querySelectorAll('[data-update-id]').forEach((el) => el.remove());
            if (title) {
              title.remove();
            }
            return;
          }

          const response = await AlertsUtilities.getSituationUpdates(item);
          if (!response || !response.data) {
            return;
          }

          // Insert section title on the 0 -> N transition.
          let titleEl = title;
          if (!titleEl) {
            body.insertAdjacentHTML(
              'afterbegin',
              AlertsUtilities.hawkAlertSituationUpdateSectionTitle()
            );
            titleEl = body.querySelector('.hawk-alert-updates-title');
          }

          const existing = new Map();
          body.querySelectorAll('[data-update-id]').forEach((el) => {
            existing.set(el.getAttribute('data-update-id'), el);
          });

          // Oldest-first so each insert-after-title pushes prior ones down,
          // leaving the newest update directly beneath the title.
          const sorted = [...response.data].sort(
            (a, b) => new Date(a.attributes.date) - new Date(b.attributes.date)
          );

          const seen = new Set();
          for (const update of sorted) {
            const id = `hawk-update-${update.attributes.date}`;
            seen.add(id);
            if (!existing.get(id)) {
              const updateEl = AlertsUtilities.createElementFromHTML(
                AlertsUtilities.hawkAlertStatusUpdateContent(update)
              );
              titleEl.after(updateEl);
            }
          }

          existing.forEach((el, id) => {
            if (!seen.has(id)) {
              el.remove();
            }
          });
        }

        function renderCampusNormal() {
          if (container.querySelector('.alert--success')) {
            return;
          }
          container.querySelectorAll('[data-alert-id]').forEach((el) => el.remove());
          container.insertAdjacentHTML('beforeend', campusNormalContent());
        }

        function campusNormalContent() {
          return `
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
      });
    },
  };
})(Drupal, drupalSettings, once);
