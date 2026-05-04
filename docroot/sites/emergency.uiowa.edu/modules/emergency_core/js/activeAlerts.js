// Active Alerts block.
//
// Polls the Hawk Alerts feed and renders alerts (with their situation
// updates) directly into the container.
//
// Unlike the global alerts block, this block owns its own markup and
// update flow rather than going through Drupal.Message so it can
// present a richer per-alert layout.
(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.activeAlerts = {
    attach: function (context, settings) {
      once('activeAlerts', '.active-alerts-container', context).forEach(function (container) {
        const au = Drupal.uiowaAlerts.AlertsUtilities;

        // Cached state from the previous successful fetch — used to detect
        // real content changes so screen readers only hear about diffs.
        // Starts empty so first-load alerts announce as "new"; first load
        // with no alerts stays silent (empty diff).
        let prevAlerts = new Map();
        let lastError = false;
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
            const response = await au.fetchAlerts();
            if (response.data.length > 0) {
              await syncAlerts(response.data);
            }
            else {
              renderCampusNormal();
            }

            const current = readAlertState();
            announceChanges(prevAlerts, current);
            prevAlerts = current;
            lastError = false;
          }
          catch (e) {
            container.innerHTML = '<p>Unable to load active alerts.</p>';
            if (!lastError) {
              Drupal.announce(Drupal.t('Unable to load active alerts.'));
              lastError = true;
            }
          }
        }

        // Read alert state straight from the DOM rather than tracking a
        // parallel JS model. The DOM is what the user actually sees, so
        // diffing against it avoids drift if a render is ever interrupted
        // or markup is mutated by something outside this behavior.
        function readAlertState() {
          const state = new Map();
          container.querySelectorAll('[data-alert-id]').forEach((el) => {
            const id = el.getAttribute('data-alert-id');
            const title = el.querySelector('.hawk-alert-label')?.textContent.trim() ?? 'Active alert';
            const updateIds = new Set(
              [...el.querySelectorAll('[data-update-id]')].map((u) => u.getAttribute('data-update-id'))
            );
            state.set(id, { title, updateIds });
          });
          return state;
        }

        function announceChanges(prev, current) {
          if (prev.size > 0 && current.size === 0) {
            Drupal.announce(Drupal.t('Campus status normal.'));
            return;
          }

          current.forEach(({ title, updateIds }, id) => {
            if (!prev.has(id)) {
              Drupal.announce(Drupal.t('New alert - @title', { '@title': title }));
              return;
            }
            const prevUpdates = prev.get(id).updateIds;
            const hasNewUpdate = [...updateIds].some((u) => !prevUpdates.has(u));
            if (hasNewUpdate) {
              Drupal.announce(Drupal.t('Updates made to @title', { '@title': title }));
            }
          });
        }

        // Reconcile the rendered alerts with the latest feed.
        //
        // Diffs by data-alert-id and changes the alert in place. Reusing
        // existing nodes preserves focus, ARIA state, and in-flight CSS
        // transitions across polls.
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
              const markup = au.fullHawkAlertMarkup(
                au.hawkAlertContent(
                  item,
                  {
                    title: item.attributes.alert,
                    displayDay: true,
                    body:`<div class="hawk-alert-body updates"></div>`,
                    link:''
                  }
                )
              );
              alertEl = au.createElementFromHTML(markup);
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

        // Sync the situation-updates list inside a single alert.
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

          const response = await au.getSituationUpdates(item);
          if (!response?.data) {
            return;
          }

          // Insert section title on the 0 -> N transition.
          let titleEl = title;
          if (!titleEl) {
            body.insertAdjacentHTML(
              'afterbegin',
              au.hawkAlertSituationUpdateSectionTitle()
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
              const updateEl = au.createElementFromHTML(
                au.hawkAlertStatusUpdateContent(update)
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
