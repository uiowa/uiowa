/**
 * @file
 * Fetch University of Iowa alerts.
 */

(($, Drupal, drupalSettings) => {
  // Attach uiowaAlertsGetAlerts behavior.
  Drupal.behaviors.uiowaAlerts = {
    attach: (context, settings) => {
      $('.block-uiowa-alerts-block', context).once('uiowaAlertsGetAlerts').each(() => {
        const messages = new Drupal.Message($('.hawk-alerts-wrapper')[0]);

        const updateAlerts = () => {
          // Get the alerts feed and track IDs as "new" alerts.
          $.ajax({
            url: drupalSettings.uiowaAlerts.source,
            dataType: "json",
            success: (response) => {
              let new_alerts = [];

              $.each(response.uihphawkalert, (i, item) => {
                let id = `hawk-alert-${item.hawkalert.date}`;
                new_alerts.push(id);

                if (!messages.select(id)) {
                  let date = new Date(item.hawkalert.date * 1000);
                  let month = date.toLocaleDateString('en-us', { month: 'long' });
                  let time = date.toLocaleTimeString('en-us', { hour: 'numeric', minute: '2-digit' })
                    .replace(' AM', 'am')
                    .replace(' PM', 'pm');

                  let alert = `
<div class="hawk-alert alert alert-danger">
<div class="hawk-alert-message">
    <span class="hawk-alert-heading">
        <span class="hawk-alert-label">Hawk Alert</span>
        <span class="hawk-alert-date">${month} ${date.getDate()}, ${date.getFullYear()} - ${time}</span>
    </span>
    <span class="hawk-alert-body">${item.hawkalert.alert}</span>
    <a class="hawk-alert-link alert-link" href=https://${item.hawkalert.more_info_link}>Visit ${item.hawkalert.more_info_link} for more information.</a>
</div>
</div>
        `;
                  messages.add(alert, {
                    id: id,
                    type: 'warning'
                  });
                }
              });

              let existing_alerts = [];

              // Get the existing alerts on the page and track IDs.
              document.querySelectorAll('.hawk-alerts-wrapper .messages').forEach( (existing_alert) => {
                existing_alerts.push(existing_alert.getAttribute('data-drupal-message-id'));
              });

              // Return any existing alerts that are not in the feed anymore.
              let difference = existing_alerts.filter(x => !new_alerts.includes(x));

              // Remove any closed alerts.
              difference.forEach((closed) => {
                messages.remove(closed);
              })
            }
          });
        };

        // Get alerts on page load.
        updateAlerts(messages);

        // Check for changes every 30 seconds.
        setInterval(updateAlerts, 30000, messages);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
