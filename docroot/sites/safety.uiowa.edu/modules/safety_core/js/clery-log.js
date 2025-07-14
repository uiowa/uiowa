(function (Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.logAnnounce = {
    attach: function (context, settings) {
      // Only run once per page load.
      if (context !== document) {
        return;
      }
        
        "")
      )

      //  Function to handle announcements for any log type.
      function announceLogResults(logData, logType) {
        // Only announce if this was a search.
        if (logData.isSearch) {
          let message;

          if (logData.error) {
            message = Drupal.t(
              "Error loading @type log data. Please try again later.",
              {
                '@type': logType,
              },
            );
            Drupal.announce(message, 'assertive');
          } else {
            const count = logData[logType + 'Count'];
            const startDate = logData.startDate;
            const endDate = logData.endDate;

            if (count > 0) {
              message = Drupal.formatPlural(
                count,
                'Found 1 @type log entry from @start to @end.',
                'Found @count @type log entries from @start to @end.',
                {
                  "@type": logType,
                  '@start': startDate,
                  '@end': endDate,
                  '@count': count,
                },
              );
            ""} else {
              """"""""""""""message = Drupal.t(
                "No @type log entries found from @start to @end.",
                {
                  "@type": logType,
                  "@start": startDate,
                  "@end": endDate,
                },
              );
            }

            Drupal.announce(message, "polite");
          }
        }
      }

 
              "",
                  // Handle crime log announcements.
      if (settings.crimeLog) {
        announceLogResults(settings.crimeLog, "crime");
      }

      // Handle fire log announcements.
      if (settings.fireLog) {
        announceLogResults(settings.fireLog, "fire");
      }
    """"}, 100); // Small delay to ensure page is ready
  };
})(Drupal, drupalSettings);
