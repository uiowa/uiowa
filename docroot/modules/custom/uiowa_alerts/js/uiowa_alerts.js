/**
 * @file
 * Fetch University of Iowa alerts.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.uiowaAlertsGetAlerts = function() {
    $.ajax({
      url: drupalSettings.uiowaAlerts.source,
      dataType: "json",
      success: function( response ) {
        if (response.uihphawkalert.length === 0 ) {
          var noAlertMessage = drupalSettings.uiowaAlerts.noAlertsMessage;
          if (!(noAlertMessage.length === 0)) {
            var noAlertContent = '<div class="hawk-alert alert alert-success" role="alert"><div class="hawk-alert-message">' + noAlertMessage + '</div></div>';
            $(".block-uiowa-alerts-block .uiowa-alerts-wrapper").html(noAlertContent);
          }
        } else {
          var allAlerts = '';
          $.each(response.uihphawkalert, function (i, item) {
            var alertDate = '<span class="hawk-alert-date">' + moment.unix(item.hawkalert.date).format('MMMM D, YYYY - H:mma') + '</span>';
            var alertHeading = '<span class="hawk-alert-heading"><span class="hawk-alert-label">Hawk Alert</span> ' + alertDate + '</span> ';
            var alertBody = '<span class="hawk-alert-body">' + item.hawkalert.alert + '</span>';
            var alertMoreInfo = ' <a class="hawk-alert-link alert-link" href=https://' + item.hawkalert.more_info_link + '>Visit ' + item.hawkalert.more_info_link + ' for more information.</a>'
            var alertContent = '<div class="hawk-alert alert alert-danger" role="alert"><div class="hawk-alert-message">' + alertHeading + alertBody + alertMoreInfo + '</div></div>';
            allAlerts += alertContent;
          });
          $(".block-uiowa-alerts-block .uiowa-alerts-wrapper").html(allAlerts);
        }
      }
    });
  };

  // Attach uiowaAlertsGetAlerts behavior.
  Drupal.behaviors.uiowaAlerts = {
    attach: function(context, settings) {
      $(".block-uiowa-alerts-block", context).once('uiowaAlertsGetAlerts').each(function() {
        // Get alerts on page load.
        Drupal.uiowaAlertsGetAlerts();

        // Check for changes every 30 seconds.
        var timer = setInterval(Drupal.uiowaAlertsGetAlerts, 30000);
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
