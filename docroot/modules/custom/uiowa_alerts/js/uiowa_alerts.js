/**
 * @file
 * Fetch University of Iowa alerts.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  // Attach uiowaAlertsGetAlerts behavior.
  Drupal.behaviors.uiowaAlerts = {
    attach: function(context, settings) {
      $('.block-uiowa-alerts-block', context).once('uiowaAlertsGetAlerts').each(function() {
        const messages = new Drupal.Message($('.hawk-alerts-wrapper')[0]);

        const getAlerts = function() {
          messages.clear();

          $.ajax({
            url: drupalSettings.uiowaAlerts.source,
            dataType: "json",
            success: function( response ) {
              $.each(response.uihphawkalert, function (i, item) {
                let id = 'hawk-alert-' + item.hawkalert.date;

                let alert = `
<div class="hawk-alert alert alert-danger">
<div class="hawk-alert-message">
    <span class="hawk-alert-heading">
        <span class="hawk-alert-label">Hawk Alert</span>
        <span class="hawk-alert-date">${moment.unix(item.hawkalert.date).format('MMMM D, YYYY - h:mma')}</span>
    </span>
    <span class="hawk-alert-body">${item.hawkalert.alert}</span>
    <a class="hawk-alert-link alert-link" href=https://${item.hawkalert.more_info_link}>Visit ${item.hawkalert.more_info_link} for more information.</a>
</div>
</div>
        `;

                messages.add(alert, {
                  id: id,
                  type: 'error'
                });
              });
            }
          });
        };

        // Get alerts on page load.
        getAlerts(messages);

        // Check for changes every 30 seconds.
        setInterval(getAlerts, 30000, messages);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
